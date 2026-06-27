"""
Nexio S.A. — Agent Envoi Multi-canal
Email (SMTP), WhatsApp Business API, Facebook Graph API.
Toutes les clés sont lues depuis .env via les variables d'environnement.
"""

import os
import json
import logging
import smtplib
import time
import requests
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
from email.utils import formatdate
import mysql.connector
from api.grok_api import GrokAPI


class AgentEnvoi:
    def __init__(self, db_config: dict):
        self.grok = GrokAPI()
        self.db   = mysql.connector.connect(**db_config)
        self.log  = logging.getLogger("AgentEnvoi")

        # ── Chargement config depuis .env ─────────────────────
        self.smtp_host     = os.getenv("SMTP_HOST",     "smtp.gmail.com")
        self.smtp_port     = int(os.getenv("SMTP_PORT", "587"))
        self.smtp_user     = os.getenv("SMTP_USER",     "")
        self.smtp_password = os.getenv("SMTP_PASSWORD", "")
        self.smtp_from     = os.getenv("SMTP_FROM",     "info@nexio.ht")
        self.smtp_from_name= os.getenv("SMTP_FROM_NAME","Nexio S.A.")

        self.wa_token      = os.getenv("WHATSAPP_TOKEN",    "")
        self.wa_phone_id   = os.getenv("WHATSAPP_PHONE_ID", "")

        self.fb_page_token = os.getenv("FACEBOOK_PAGE_TOKEN","")
        self.fb_page_id    = os.getenv("FACEBOOK_PAGE_ID",  "")

    def _cursor(self):
        if not self.db.is_connected():
            self.db.reconnect(attempts=3, delay=2)
        return self.db.cursor(dictionary=True)

    # ══════════════════════════════════════════════════════════
    #  EMAIL SMTP
    # ══════════════════════════════════════════════════════════
    def envoyer_email(self, to_email: str, to_name: str,
                       subject: str, html_body: str,
                       plain_body: str = "") -> dict:
        """Envoie un email via SMTP (Gmail / tout serveur SMTP)."""
        if not self.smtp_user or not self.smtp_password:
            self.log.warning("SMTP non configuré — email simulé vers %s", to_email)
            return {"statut": "simulé", "destinataire": to_email, "note": "Configurez SMTP_USER et SMTP_PASSWORD dans .env"}

        try:
            msg = MIMEMultipart("alternative")
            msg["Subject"] = subject
            msg["From"]    = f"{self.smtp_from_name} <{self.smtp_from}>"
            msg["To"]      = f"{to_name} <{to_email}>"
            msg["Date"]    = formatdate(localtime=True)

            if plain_body:
                msg.attach(MIMEText(plain_body, "plain", "utf-8"))
            msg.attach(MIMEText(html_body, "html", "utf-8"))

            with smtplib.SMTP(self.smtp_host, self.smtp_port, timeout=20) as server:
                server.ehlo()
                server.starttls()
                server.login(self.smtp_user, self.smtp_password)
                server.sendmail(self.smtp_from, [to_email], msg.as_string())

            self.log.info("Email envoyé à %s — %s", to_email, subject)
            return {"statut": "envoyé", "destinataire": to_email}

        except smtplib.SMTPAuthenticationError:
            self.log.error("SMTP auth error pour %s", to_email)
            return {"statut": "erreur", "detail": "Authentification SMTP échouée. Vérifiez SMTP_USER/SMTP_PASSWORD dans .env"}
        except Exception as e:
            self.log.error("Erreur email %s : %s", to_email, e)
            return {"statut": "erreur", "detail": str(e)}

    def _build_email_html(self, nom: str, sujet: str,
                           contenu: str, appel_action: str = "") -> str:
        """Construit un email HTML professionnel aux couleurs Nexio."""
        cta = ""
        if appel_action:
            cta = f'<div style="text-align:center;margin:24px 0;"><a href="http://localhost/nexio_final" style="background:#1D4ED8;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;">{appel_action}</a></div>'
        return f"""<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#06080F;font-family:'Inter',Arial,sans-serif;">
<table width="100%" style="max-width:600px;margin:20px auto;background:#111827;border-radius:16px;overflow:hidden;border:1px solid rgba(255,255,255,.08);">
  <tr><td style="background:linear-gradient(135deg,#0D1F40,#06080F);padding:28px 32px;text-align:center;">
    <div style="font-size:22px;font-weight:900;color:#EDF2F7;">Nexio<span style="color:#00C8FF">.</span>ht</div>
    <div style="font-size:11px;color:#64748B;margin-top:4px;">Votre partenaire tech à Port-au-Prince</div>
  </td></tr>
  <tr><td style="padding:28px 32px;">
    <h2 style="font-size:18px;font-weight:800;color:#00C8FF;margin:0 0 12px;">{sujet}</h2>
    <p style="font-size:15px;color:#CBD5E1;margin:0 0 8px;">Bonjour {nom},</p>
    <div style="font-size:14px;color:#94A3B8;line-height:1.7;white-space:pre-line;">{contenu}</div>
    {cta}
  </td></tr>
  <tr><td style="background:#0D1117;padding:16px 32px;text-align:center;">
    <p style="font-size:11px;color:#374151;margin:0;">📍 Delmas, Port-au-Prince · 📞 4810-8541 · Lun-Sam 8h-18h</p>
    <p style="font-size:10px;color:#1F2937;margin:6px 0 0;">© 2025 Nexio S.A. — Se désabonner</p>
  </td></tr>
</table>
</body></html>"""

    # ══════════════════════════════════════════════════════════
    #  WHATSAPP BUSINESS API
    # ══════════════════════════════════════════════════════════
    def envoyer_whatsapp(self, phone: str, message: str) -> dict:
        """Envoie un message WhatsApp via l'API Meta Business."""
        if not self.wa_token or not self.wa_phone_id:
            self.log.warning("WhatsApp non configuré — message simulé vers %s", phone)
            return {"statut": "simulé", "destinataire": phone, "note": "Configurez WHATSAPP_TOKEN et WHATSAPP_PHONE_ID dans .env"}

        # Nettoyage du numéro (format international sans +)
        phone_clean = phone.replace("+", "").replace("-", "").replace(" ", "")
        if not phone_clean.startswith("509"):
            phone_clean = "509" + phone_clean  # Préfixe Haïti

        url = f"https://graph.facebook.com/v18.0/{self.wa_phone_id}/messages"
        payload = {
            "messaging_product": "whatsapp",
            "to": phone_clean,
            "type": "text",
            "text": {"body": message}
        }
        headers = {
            "Authorization": f"Bearer {self.wa_token}",
            "Content-Type": "application/json"
        }
        try:
            resp = requests.post(url, json=payload, headers=headers, timeout=15)
            resp.raise_for_status()
            data = resp.json()
            self.log.info("WhatsApp envoyé à %s", phone_clean)
            return {"statut": "envoyé", "destinataire": phone_clean, "message_id": data.get("messages", [{}])[0].get("id")}
        except requests.exceptions.HTTPError as e:
            err_detail = e.response.json() if e.response else str(e)
            self.log.error("WhatsApp erreur %s : %s", phone_clean, err_detail)
            return {"statut": "erreur", "detail": str(err_detail)}
        except Exception as e:
            return {"statut": "erreur", "detail": str(e)}

    # ══════════════════════════════════════════════════════════
    #  FACEBOOK GRAPH API
    # ══════════════════════════════════════════════════════════
    def poster_facebook(self, message: str, link: str = "") -> dict:
        """Poste une publication sur la page Facebook Nexio."""
        if not self.fb_page_token or not self.fb_page_id:
            self.log.warning("Facebook non configuré — publication simulée")
            return {"statut": "simulé", "note": "Configurez FACEBOOK_PAGE_TOKEN et FACEBOOK_PAGE_ID dans .env"}

        url = f"https://graph.facebook.com/v18.0/{self.fb_page_id}/feed"
        payload = {"message": message, "access_token": self.fb_page_token}
        if link:
            payload["link"] = link
        try:
            resp = requests.post(url, data=payload, timeout=15)
            resp.raise_for_status()
            data = resp.json()
            self.log.info("Facebook post créé : %s", data.get("id"))
            return {"statut": "posté", "post_id": data.get("id")}
        except Exception as e:
            return {"statut": "erreur", "detail": str(e)}

    # ══════════════════════════════════════════════════════════
    #  ENVOI CAMPAGNE
    # ══════════════════════════════════════════════════════════
    def envoyer_campagne(self, id_campagne: int,
                          envoi_immediat: bool = True) -> dict:
        """Envoie une campagne complète à tous ses destinataires."""
        cur = self._cursor()

        # Charger campagne
        cur.execute("SELECT * FROM campagnes WHERE id_campagne=%s", (id_campagne,))
        camp = cur.fetchone()
        if not camp:
            return {"error": "Campagne introuvable"}

        canal    = camp.get("canal", "Email")
        type_c   = camp.get("type",  "globale")
        segment  = camp.get("segment", "")
        id_user  = camp.get("id_user_cible")

        # Récupérer destinataires
        if id_user:
            cur.execute("SELECT id_user,prenom,nom,email,telephone FROM users WHERE id_user=%s", (id_user,))
            destinataires = cur.fetchall()
        elif segment:
            cur.execute("""
                SELECT u.id_user,u.prenom,u.nom,u.email,u.telephone
                FROM users u JOIN profils_ia p ON u.id_user=p.id_user
                WHERE p.segment=%s AND u.statut='Actif'
            """, (segment,))
            destinataires = cur.fetchall()
        else:
            # Globale — tous clients actifs
            cur.execute("""
                SELECT u.id_user,u.prenom,u.nom,u.email,u.telephone
                FROM users u JOIN roles r ON u.id_role=r.id_role
                WHERE r.nom='Client' AND u.statut='Actif'
            """)
            destinataires = cur.fetchall()

        if not destinataires:
            return {"statut": "aucun destinataire", "campagne": id_campagne}

        nb_ok    = 0
        nb_err   = 0
        resultats = []

        sujet   = camp.get("titre_ia") or camp.get("nom", "Message de Nexio S.A.")
        contenu = camp.get("contenu", "")
        contenu_email = camp.get("contenu_email") or contenu
        contenu_wa    = camp.get("contenu_whatsapp") or contenu
        contenu_fb    = camp.get("contenu_facebook") or contenu
        appel         = camp.get("appel_action", "Voir nos offres")

        for dest in destinataires:
            nom_complet = f"{dest['prenom']} {dest['nom']}"
            result_dest = {"user": dest["id_user"], "canal": canal}

            if canal in ("Email", "Multi-canal"):
                if dest.get("email"):
                    html = self._build_email_html(dest["prenom"], sujet, contenu_email, appel)
                    r = self.envoyer_email(dest["email"], nom_complet, sujet, html, contenu_email)
                    result_dest["email"] = r["statut"]
                    if r["statut"] in ("envoyé", "simulé"):
                        nb_ok += 1
                    else:
                        nb_err += 1

                    # Log en DB
                    try:
                        cur.execute("""
                            INSERT INTO messages_marketing(id_campagne,id_user,canal,contenu,statut)
                            VALUES(%s,%s,'Email',%s,%s)
                        """, (id_campagne, dest["id_user"], contenu_email,
                              "Envoyé" if r["statut"] in ("envoyé","simulé") else "Échec"))
                        self.db.commit()
                    except Exception:
                        pass

            if canal in ("WhatsApp", "Multi-canal"):
                phone = dest.get("telephone", "")
                if phone:
                    r = self.envoyer_whatsapp(phone, contenu_wa)
                    result_dest["whatsapp"] = r["statut"]
                    if r["statut"] in ("envoyé", "simulé"):
                        nb_ok += 1

            resultats.append(result_dest)
            time.sleep(0.1)  # Rate limiting

        if canal == "Facebook":
            r = self.poster_facebook(contenu_fb, "http://localhost/nexio_final")
            nb_ok += 1 if r["statut"] in ("posté","simulé") else 0

        # Mettre à jour statut campagne
        try:
            cur.execute("""
                UPDATE campagnes
                SET statut='Envoyée', date_envoi=NOW(),
                    nb_destins=%s, nb_envoyes=%s
                WHERE id_campagne=%s
            """, (len(destinataires), nb_ok, id_campagne))
            self.db.commit()
        except Exception as e:
            self.log.error("Update campagne : %s", e)

        self.log.info("Campagne %d envoyée : %d OK / %d erreurs",
                      id_campagne, nb_ok, nb_err)
        return {
            "campagne": id_campagne,
            "destinataires": len(destinataires),
            "envoyes": nb_ok,
            "erreurs": nb_err,
            "statut": "Envoyée",
            "details": resultats[:20],  # Limité pour la réponse
        }

    def envoyer_test(self, email: str, message: str,
                      canal: str = "Email") -> dict:
        """Envoi de test à une adresse unique."""
        if canal == "Email":
            html = self._build_email_html("Test", "Test Nexio IA", message, "Voir la boutique")
            return self.envoyer_email(email, "Équipe Test", "Test Nexio S.A.", html, message)
        elif canal == "WhatsApp":
            return self.envoyer_whatsapp(email, message)  # email = numéro ici
        elif canal == "Facebook":
            return self.poster_facebook(message)
        return {"statut": "canal inconnu"}
