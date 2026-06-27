"""Nexio S.A. — Agent 5 : Prévision des ventes et ruptures."""
import json, logging, time
import mysql.connector
from api.grok_api import GrokAPI


class AgentPrevision:
    def __init__(self, db_config: dict):
        self.grok = GrokAPI()
        self.db   = mysql.connector.connect(**db_config)
        self.log  = logging.getLogger("Agent5.Prevision")

    def _cursor(self):
        if not self.db.is_connected():
            self.db.reconnect(attempts=3, delay=2)
        return self.db.cursor(dictionary=True)

    def prevoir_ventes(self) -> dict:
        cur = self._cursor()
        cur.execute("""
            SELECT DATE(date_commande) AS jour,
                   COUNT(*) AS nb_commandes,
                   COALESCE(SUM(montant),0) AS ca
            FROM commandes
            WHERE date_commande >= NOW() - INTERVAL 30 DAY AND statut != 'Annulée'
            GROUP BY jour ORDER BY jour
        """)
        historique = [{"date": str(r['jour']), "commandes": r['nb_commandes'], "ca": float(r['ca'])} for r in cur.fetchall()]
        return self.grok.forecast_sales(historique)

    def prevoir_ruptures(self) -> list:
        cur = self._cursor()
        cur.execute("""
            SELECT p.nom, p.quantite, p.seuil_alerte,
                   COALESCE(SUM(dc.quantite)/30,0) AS vente_jour
            FROM produits p
            LEFT JOIN details_commandes dc ON p.id_produit=dc.id_produit
            LEFT JOIN commandes c ON dc.id_commande=c.id_commande
                AND c.date_commande >= NOW() - INTERVAL 30 DAY
            GROUP BY p.id_produit
            HAVING p.quantite > 0
        """)
        produits = cur.fetchall()
        ruptures_prevues = []
        for p in produits:
            if p['vente_jour'] > 0:
                jours_restants = int(p['quantite'] / p['vente_jour'])
                if jours_restants <= 14:
                    ruptures_prevues.append({
                        "produit": p['nom'],
                        "stock": p['quantite'],
                        "jours_avant_rupture": jours_restants,
                        "urgence": "critique" if jours_restants <= 3 else "élevée" if jours_restants <= 7 else "modérée"
                    })
        return sorted(ruptures_prevues, key=lambda x: x['jours_avant_rupture'])
