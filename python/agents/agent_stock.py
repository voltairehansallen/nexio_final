"""
Nexio S.A. — Agent 6 : Gestion Intelligente du Stock
Détecte ruptures, seuils, produits inactifs, produits sans image.
"""

import json, logging, time
import mysql.connector
from api.grok_api import GrokAPI


class AgentStock:
    def __init__(self, db_config: dict):
        self.grok = GrokAPI()
        self.db   = mysql.connector.connect(**db_config)
        self.log  = logging.getLogger("Agent6.Stock")

    def _cursor(self):
        if not self.db.is_connected():
            self.db.reconnect(attempts=3, delay=2)
        return self.db.cursor(dictionary=True)

    def analyser_stocks(self) -> dict:
        """Analyse complète des stocks avec alertes IA."""
        cur   = self._cursor()

        # Ruptures de stock
        cur.execute("SELECT id_produit,nom,quantite FROM produits WHERE statut='Rupture' OR quantite=0")
        ruptures = cur.fetchall()

        # Seuils d'alerte
        cur.execute("SELECT id_produit,nom,quantite,seuil_alerte FROM produits WHERE quantite<=seuil_alerte AND quantite>0")
        alertes = cur.fetchall()

        # Produits sans image
        cur.execute("SELECT id_produit,nom FROM produits WHERE image IS NULL OR image=''")
        sans_image = cur.fetchall()

        # Produits inactifs (jamais commandés)
        cur.execute("""
            SELECT p.id_produit,p.nom,p.quantite FROM produits p
            WHERE p.id_produit NOT IN (SELECT DISTINCT id_produit FROM details_commandes)
              AND p.date_ajout < NOW() - INTERVAL 30 DAY
        """)
        inactifs = cur.fetchall()

        rapport = {
            "ruptures":   ruptures,
            "alertes":    alertes,
            "sans_image": sans_image,
            "inactifs":   inactifs,
            "nb_ruptures":   len(ruptures),
            "nb_alertes":    len(alertes),
            "nb_sans_image": len(sans_image),
            "nb_inactifs":   len(inactifs),
        }

        # Recommandations IA
        prompt = (
            f"Analyse cet état des stocks Nexio S.A. :\n"
            f"- {len(ruptures)} produit(s) en rupture\n"
            f"- {len(alertes)} produit(s) sous seuil d'alerte\n"
            f"- {len(inactifs)} produit(s) inactifs depuis 30j\n"
            f"Ruptures : {[p['nom'] for p in ruptures[:5]]}\n"
            "Donne des recommandations d'action prioritaires. Sois concis."
        )
        rapport["recommandations_ia"] = self.grok.generate(prompt, max_tokens=400)

        self.log.info("Analyse stock terminée : %d ruptures, %d alertes", len(ruptures), len(alertes))
        return rapport

    def quantites_a_commander(self) -> list:
        """Calcule les quantités à commander pour chaque produit."""
        cur = self._cursor()
        cur.execute("""
            SELECT p.id_produit, p.nom, p.quantite, p.seuil_alerte,
                   COALESCE(AVG(dc.quantite),0) AS vente_moy_30j
            FROM produits p
            LEFT JOIN details_commandes dc ON p.id_produit=dc.id_produit
            LEFT JOIN commandes c ON dc.id_commande=c.id_commande
                AND c.date_commande >= NOW() - INTERVAL 30 DAY
            WHERE p.quantite <= p.seuil_alerte * 2
            GROUP BY p.id_produit
        """)
        produits = cur.fetchall()
        for p in produits:
            p['qte_a_commander'] = max(0, int(p['vente_moy_30j'] * 2 - p['quantite']))
        return produits
