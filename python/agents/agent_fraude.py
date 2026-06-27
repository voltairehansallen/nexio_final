"""Nexio S.A. — Agent 10 : Détection de fraude."""
import json, logging
import mysql.connector
from api.grok_api import GrokAPI


class AgentFraude:
    def __init__(self, db_config: dict):
        self.grok = GrokAPI()
        self.db   = mysql.connector.connect(**db_config)
        self.log  = logging.getLogger("Agent10.Fraude")

    def _cursor(self):
        if not self.db.is_connected():
            self.db.reconnect(attempts=3, delay=2)
        return self.db.cursor(dictionary=True)

    def analyser_commande(self, id_commande: int) -> dict:
        cur = self._cursor()
        cur.execute("""
            SELECT c.*, u.email, u.date_creation,
                   COUNT(c2.id_commande) AS historique_commandes,
                   COALESCE(AVG(c2.montant),0) AS panier_moyen_historique
            FROM commandes c
            JOIN users u ON c.id_user=u.id_user
            LEFT JOIN commandes c2 ON c2.id_user=c.id_user AND c2.id_commande!=c.id_commande
            WHERE c.id_commande=%s GROUP BY c.id_commande
        """, (id_commande,))
        cmd = cur.fetchone()
        if not cmd:
            return {"erreur": "Commande introuvable"}

        order_data = {
            "id_commande": id_commande,
            "montant": float(cmd['montant']),
            "panier_moyen_historique": float(cmd['panier_moyen_historique']),
            "historique_commandes": int(cmd['historique_commandes']),
            "compte_age_jours": (cmd['date_creation'] and (cmd['date_commande'] - cmd['date_creation']).days) or 0,
            "statut": cmd['statut'],
        }
        result = self.grok.detect_fraud(order_data)
        self.log.info("Commande %d — risque: %s (score: %s)", id_commande, result.get('risque'), result.get('score'))
        return result

    def scanner_commandes_recentes(self, limit: int = 50) -> list:
        cur = self._cursor()
        cur.execute("SELECT id_commande FROM commandes ORDER BY date_commande DESC LIMIT %s", (limit,))
        ids = [r['id_commande'] for r in cur.fetchall()]
        results = []
        for cid in ids:
            r = self.analyser_commande(cid)
            if r.get('risque') in ('moyen', 'élevé'):
                r['id_commande'] = cid
                results.append(r)
        return results
