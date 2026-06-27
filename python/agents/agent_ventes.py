"""
Nexio S.A. — Agent 4 : Analyse des Ventes
CA, bénéfices, panier moyen, taux de conversion, rapport JSON.
"""

import json, logging, time
import mysql.connector
from datetime import datetime
from api.grok_api import GrokAPI


class AgentVentes:
    def __init__(self, db_config: dict):
        self.grok = GrokAPI()
        self.db   = mysql.connector.connect(**db_config)
        self.log  = logging.getLogger("Agent4.Ventes")

    def _cursor(self):
        if not self.db.is_connected():
            self.db.reconnect(attempts=3, delay=2)
        return self.db.cursor(dictionary=True)

    def rapport_complet(self, periode_jours: int = 30) -> dict:
        """Génère un rapport complet des ventes en JSON."""
        start = time.time()
        cur   = self._cursor()

        # CA et commandes
        cur.execute("""
            SELECT COUNT(*) AS nb_commandes,
                   COALESCE(SUM(montant),0) AS ca_total,
                   COALESCE(AVG(montant),0) AS panier_moyen,
                   COALESCE(SUM(montant),0) AS ca_periode
            FROM commandes
            WHERE date_commande >= NOW() - INTERVAL %s DAY
              AND statut != 'Annulée'
        """, (periode_jours,))
        stats = cur.fetchone()

        # Bénéfices
        cur.execute("""
            SELECT COALESCE(SUM((dc.prix - p.cout) * dc.quantite),0) AS benefices
            FROM details_commandes dc
            JOIN commandes c ON dc.id_commande=c.id_commande
            JOIN produits p ON dc.id_produit=p.id_produit
            WHERE c.date_commande >= NOW() - INTERVAL %s DAY
              AND c.statut != 'Annulée' AND p.cout IS NOT NULL
        """, (periode_jours,))
        benef = cur.fetchone()

        # Top produits
        cur.execute("""
            SELECT p.nom, SUM(dc.quantite) AS qte_vendue,
                   SUM(dc.quantite*dc.prix) AS ca
            FROM details_commandes dc
            JOIN produits p ON dc.id_produit=p.id_produit
            JOIN commandes c ON dc.id_commande=c.id_commande
            WHERE c.date_commande >= NOW() - INTERVAL %s DAY
              AND c.statut != 'Annulée'
            GROUP BY dc.id_produit ORDER BY qte_vendue DESC LIMIT 10
        """, (periode_jours,))
        top_produits = cur.fetchall()

        # Top catégories
        cur.execute("""
            SELECT cat.nom AS categorie, SUM(dc.quantite) AS qte_vendue,
                   SUM(dc.quantite*dc.prix) AS ca
            FROM details_commandes dc
            JOIN produits p ON dc.id_produit=p.id_produit
            JOIN sous_categories sc ON p.id_sous_categorie=sc.id_sous_categorie
            JOIN categories cat ON sc.id_categorie=cat.id_categorie
            JOIN commandes c ON dc.id_commande=c.id_commande
            WHERE c.date_commande >= NOW() - INTERVAL %s DAY
            GROUP BY cat.id_categorie ORDER BY ca DESC LIMIT 5
        """, (periode_jours,))
        top_categories = cur.fetchall()

        # Taux de conversion (clients avec commandes / total clients)
        cur.execute("SELECT COUNT(DISTINCT id_user) FROM commandes WHERE date_commande >= NOW() - INTERVAL %s DAY", (periode_jours,))
        clients_actifs = int(cur.fetchone()['COUNT(DISTINCT id_user)'])
        cur.execute("SELECT COUNT(*) FROM users WHERE id_role=(SELECT id_role FROM roles WHERE nom='Client')")
        total_clients  = int(cur.fetchone()['COUNT(*)']) or 1
        taux_conversion = round(clients_actifs / total_clients * 100, 1)

        rapport = {
            "periode_jours": periode_jours,
            "date_rapport": datetime.now().strftime("%Y-%m-%d %H:%M"),
            "ca_total":        float(stats['ca_total']),
            "ca_periode":      float(stats['ca_periode']),
            "benefices":       float(benef['benefices']),
            "nb_commandes":    int(stats['nb_commandes']),
            "panier_moyen":    float(stats['panier_moyen']),
            "taux_conversion": taux_conversion,
            "clients_actifs":  clients_actifs,
            "top_produits":    top_produits,
            "top_categories":  top_categories,
        }

        # Analyse IA
        prompt = (
            f"Analyse ces données de ventes Nexio S.A. sur {periode_jours} jours :\n"
            f"{json.dumps(rapport, ensure_ascii=False, default=str)}\n"
            "Donne 3 insights clés et 2 recommandations d'action. Sois concis."
        )
        rapport["analyse_ia"] = self.grok.generate(prompt, max_tokens=500)

        self.log.info("Rapport ventes généré en %ss", round(time.time()-start,2))
        return rapport
