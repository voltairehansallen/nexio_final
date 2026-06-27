"""
Nexio S.A. — Agent Campagne IA
Génère campagnes multi-canal personnalisées ou par segment.
"""

import json, logging, time
import mysql.connector
from api.grok_api import GrokAPI


class AgentCampagneIA:
    def __init__(self, db_config: dict):
        self.grok = GrokAPI()
        self.db   = mysql.connector.connect(**db_config)
        self.log  = logging.getLogger("AgentCampagneIA")

    def _cursor(self):
        if not self.db.is_connected():
            self.db.reconnect(attempts=3, delay=2)
        return self.db.cursor(dictionary=True)

    def generer_campagne(
        self,
        nom: str,
        canal: str = "Email",
        type_camp: str = "globale",
        segment: str = "",
        id_user: int = None,
    ) -> dict:
        """Génère titre, slogan, messages multi-canal avec GrokCloud."""
        start = time.time()
        cur   = self._cursor()

        # Contexte selon type
        contexte = ""
        if id_user:
            # Profil individuel
            try:
                cur.execute("SELECT * FROM profils_ia WHERE id_user=%s", (id_user,))
                profil = cur.fetchone()
                cur.execute("SELECT prenom,nom FROM users WHERE id_user=%s", (id_user,))
                user = cur.fetchone()
                if profil and user:
                    contexte = (
                        f"Client : {user['prenom']} {user['nom']}\n"
                        f"Segment : {profil.get('segment','standard')}\n"
                        f"Catégorie préférée : {profil.get('categorie_preferee','')}\n"
                        f"Budget moyen : {profil.get('budget_moyen',0):,.0f} HTG\n"
                        f"Score achat : {profil.get('score_achat',0)}/100\n"
                        f"Centres d'intérêt : {profil.get('centres_interet','')}\n"
                    )
            except Exception as e:
                self.log.warning("Profil user non trouvé : %s", e)

        elif segment:
            # Segment
            try:
                cur.execute("""
                    SELECT COUNT(*) AS nb, AVG(budget_moyen) AS bud_moy
                    FROM profils_ia WHERE segment=%s
                """, (segment,))
                seg_stats = cur.fetchone()
                contexte = (
                    f"Segment cible : {segment}\n"
                    f"Nombre de clients : {int(seg_stats.get('nb',0) or 0)}\n"
                    f"Budget moyen : {float(seg_stats.get('bud_moy',0) or 0):,.0f} HTG\n"
                )
            except Exception:
                contexte = f"Segment cible : {segment}\n"
        else:
            contexte = "Audience : tous les clients Nexio S.A.\n"

        system = (
            "Tu es un expert en marketing digital pour Nexio S.A., "
            "une entreprise haïtienne de matériel informatique à Port-au-Prince. "
            "Crée des messages percutants, en français, adaptés au contexte haïtien."
        )

        prompt = (
            f"Crée une campagne marketing complète.\n\n"
            f"Contexte :\n{contexte}\n"
            f"Nom de la campagne : {nom}\n"
            f"Canal principal : {canal}\n\n"
            "Réponds UNIQUEMENT en JSON :\n"
            "{\n"
            '  "titre": "Titre accrocheur (max 80 chars)",\n'
            '  "slogan": "Slogan mémorable (max 60 chars)",\n'
            '  "email": "Message email complet (HTML possible, 150-300 mots)",\n'
            '  "whatsapp": "Message WhatsApp concis (max 200 chars avec emojis)",\n'
            '  "facebook": "Publication Facebook engageante (max 280 chars)",\n'
            '  "appel_action": "Texte du bouton CTA"\n'
            "}"
        )

        raw = self.grok.generate(prompt, system=system, max_tokens=1200)
        try:
            raw = raw.strip().lstrip("```json").lstrip("```").rstrip("```").strip()
            result = json.loads(raw)
        except json.JSONDecodeError:
            result = {
                "titre":        nom,
                "slogan":       "La technologie à votre portée",
                "email":        f"Découvrez nos offres exclusives chez Nexio S.A. — {nom}",
                "whatsapp":     f"🎯 {nom} | Nexio S.A. Port-au-Prince | 4810-8541",
                "facebook":     f"📢 {nom} — Nexio S.A., votre partenaire tech #1 en Haïti !",
                "appel_action": "Découvrir l'offre",
            }

        result['duree_s'] = round(time.time()-start, 2)
        self.log.info("Campagne générée : '%s' en %ss", nom, result['duree_s'])
        return result

    def generer_campagnes_segments(self) -> list:
        """Génère une campagne pour chaque segment existant."""
        cur = self._cursor()
        try:
            cur.execute("SELECT DISTINCT segment FROM profils_ia WHERE segment IS NOT NULL AND segment!='' ORDER BY segment")
            segments = [r['segment'] for r in cur.fetchall()]
        except Exception:
            segments = ['gamers','entreprises','étudiants','fidèles','nouveaux clients']

        results = []
        for seg in segments:
            camp = self.generer_campagne(
                nom=f"Campagne {seg.capitalize()} — {time.strftime('%B %Y')}",
                canal="Multi-canal",
                type_camp="segment",
                segment=seg,
            )
            # Sauvegarde en DB
            try:
                cur.execute("""
                    INSERT INTO campagnes(nom,canal,type,segment,titre_ia,slogan,contenu_email,
                    contenu_whatsapp,contenu_facebook,appel_action,statut)
                    VALUES(%s,'Multi-canal','segment',%s,%s,%s,%s,%s,%s,%s,'Brouillon')
                """, (
                    camp.get('titre',seg), seg,
                    camp.get('titre',''), camp.get('slogan',''),
                    camp.get('email',''), camp.get('whatsapp',''),
                    camp.get('facebook',''), camp.get('appel_action',''),
                ))
                self.db.commit()
                camp['id_campagne'] = cur.lastrowid
                camp['segment']     = seg
            except Exception as e:
                self.log.warning("Sauvegarde campagne ignorée : %s", e)
            results.append(camp)
            time.sleep(0.5)

        return results
