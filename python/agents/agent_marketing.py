"""
Nexio S.A. — Agent 3 : Marketing Intelligent
Génère et envoie campagnes Email, WhatsApp, Facebook, Notifications.
Déclencheurs : baisse prix, retour stock, anniversaire, panier abandonné.
"""

import json, logging, time
import mysql.connector
from datetime import datetime, timedelta
from api.grok_api import GrokAPI


class AgentMarketing:
    def __init__(self, db_config: dict):
        self.grok = GrokAPI()
        self.db   = mysql.connector.connect(**db_config)
        self.log  = logging.getLogger("Agent3.Marketing")

    def _cursor(self):
        if not self.db.is_connected():
            self.db.reconnect(attempts=3, delay=2)
        return self.db.cursor(dictionary=True)

    def _save_message(self, id_campagne: int, id_user: int, canal: str, contenu: str):
        try:
            cur = self._cursor()
            cur.execute(
                "INSERT INTO messages_marketing(id_campagne,id_user,canal,contenu,statut,date_envoi) VALUES(%s,%s,%s,%s,'Envoyé',NOW())",
                (id_campagne, id_user, canal, contenu)
            )
            self.db.commit()
        except Exception as e:
            self.log.warning("Sauvegarde message ignorée : %s", e)

    def generer_campagne(self, id_campagne: int) -> dict:
        """Génère les messages pour une campagne existante."""
        cur = self._cursor()
        cur.execute("SELECT * FROM campagnes WHERE id_campagne=%s", (id_campagne,))
        camp = cur.fetchone()
        if not camp:
            return {"error": "Campagne introuvable"}

        # Récupère clients ciblés
        cur.execute("""
            SELECT u.id_user, u.nom, u.prenom, u.email, i.categorie AS interet
            FROM users u
            LEFT JOIN interets i ON u.id_user=i.id_user
            WHERE u.id_role=(SELECT id_role FROM roles WHERE nom='Client')
            LIMIT 100
        """)
        clients = cur.fetchall()

        messages_generees = 0
        for client in clients:
            segment = client.get('interet') or 'Général'
            context = (
                f"Client : {client['prenom']} {client['nom']}\n"
                f"Segment : {segment}\n"
                f"Campagne : {camp['nom']}\n"
                f"Message original : {camp['contenu']}"
            )
            msg = self.grok.generate_campaign(
                canal   = camp.get('canal', 'Email'),
                segment = segment,
                context = context
            )
            self._save_message(id_campagne, client['id_user'], camp.get('canal','Email'), msg)
            messages_generees += 1

        # Met à jour statut campagne
        cur.execute("UPDATE campagnes SET statut='Envoyée', date_envoi=NOW() WHERE id_campagne=%s", (id_campagne,))
        self.db.commit()

        self.log.info("Campagne %d : %d messages générés", id_campagne, messages_generees)
        return {"campagne": id_campagne, "messages": messages_generees, "statut": "Envoyée"}

    def detecter_paniers_abandonnes(self) -> list:
        """Détecte les paniers non convertis depuis 24h."""
        cur = self._cursor()
        cur.execute("""
            SELECT DISTINCT p.id_user, u.prenom, u.nom, u.email,
                   COUNT(p.id_panier) AS nb_articles, SUM(pr.prix) AS valeur
            FROM panier p
            JOIN users u ON p.id_user=u.id_user
            JOIN produits pr ON p.id_produit=pr.id_produit
            WHERE p.date_ajout < NOW() - INTERVAL 24 HOUR
              AND p.id_user NOT IN (
                  SELECT id_user FROM commandes WHERE date_commande > NOW() - INTERVAL 24 HOUR
              )
            GROUP BY p.id_user
        """)
        paniers = cur.fetchall()

        for p in paniers:
            msg = self.grok.generate_campaign(
                canal   = "Email",
                segment = "Panier abandonné",
                context = f"Client {p['prenom']} a {p['nb_articles']} article(s) dans son panier d'une valeur de {p['valeur']} HTG. Incite-le à finaliser son achat."
            )
            self.log.info("Panier abandonné user %d — message généré", p['id_user'])
            p['message_relance'] = msg

        return paniers

    def detecter_retour_stock(self) -> list:
        """Détecte les produits revenus en stock."""
        cur = self._cursor()
        cur.execute("""
            SELECT p.id_produit, p.nom, p.prix
            FROM produits p
            WHERE p.statut='Disponible' AND p.quantite > 0
              AND p.date_ajout > NOW() - INTERVAL 1 DAY
        """)
        produits = cur.fetchall()
        for p in produits:
            p['message'] = self.grok.generate_campaign(
                canal="WhatsApp",
                segment="Général",
                context=f"Le produit '{p['nom']}' est de nouveau disponible à {p['prix']} HTG."
            )
        return produits
