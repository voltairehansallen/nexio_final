"""
Nexio S.A. — Agent 1 : Analyse Comportementale
Analyse connexions, recherches, clics, paniers, commandes.
Calcule scores d'engagement, fidélité, préférences.
"""

import json
import logging
import time
import mysql.connector
from datetime import datetime
from api.grok_api import GrokAPI


class AgentComportemental:
    def __init__(self, db_config: dict):
        self.grok  = GrokAPI()
        self.db    = mysql.connector.connect(**db_config)
        self.log   = logging.getLogger("Agent1.Comportemental")

    def _cursor(self):
        if not self.db.is_connected():
            self.db.reconnect(attempts=3, delay=2)
        return self.db.cursor(dictionary=True)

    def analyser_utilisateur(self, id_user: int) -> dict:
        """Analyse complète du comportement d'un utilisateur."""
        start = time.time()
        cur   = self._cursor()

        # Historique commandes
        cur.execute("""
            SELECT COUNT(*) AS nb_cmd, COALESCE(SUM(montant),0) AS ca_total,
                   COALESCE(AVG(montant),0) AS panier_moyen
            FROM commandes WHERE id_user=%s AND statut != 'Annulée'
        """, (id_user,))
        cmd_stats = cur.fetchone()

        # Produits consultés (interactions)
        cur.execute("""
            SELECT p.nom, c.nom AS categorie, COUNT(*) AS nb_vues
            FROM interactions i
            JOIN produits p ON i.id_produit = p.id_produit
            LEFT JOIN sous_categories sc ON p.id_sous_categorie = sc.id_sous_categorie
            LEFT JOIN categories c ON sc.id_categorie = c.id_categorie
            WHERE i.id_user=%s
            GROUP BY p.id_produit ORDER BY nb_vues DESC LIMIT 10
        """, (id_user,))
        produits_vus = cur.fetchall()

        # Catégories préférées
        cur.execute("""
            SELECT c.nom AS categorie, COUNT(*) AS score
            FROM interactions i
            JOIN produits p ON i.id_produit = p.id_produit
            LEFT JOIN sous_categories sc ON p.id_sous_categorie = sc.id_sous_categorie
            LEFT JOIN categories c ON sc.id_categorie = c.id_categorie
            WHERE i.id_user=%s
            GROUP BY c.nom ORDER BY score DESC LIMIT 5
        """, (id_user,))
        categories_pref = cur.fetchall()

        # Score engagement (formule)
        nb_cmd    = int(cmd_stats['nb_cmd'])
        ca_total  = float(cmd_stats['ca_total'])
        nb_vues   = sum(p['nb_vues'] for p in produits_vus)
        engagement = min(100, int((nb_cmd * 20) + (nb_vues * 2) + (ca_total / 10000)))
        fidelite   = min(100, int(nb_cmd * 15 + (1 if ca_total > 50000 else 0) * 10))

        data = {
            "id_user": id_user,
            "nb_commandes": nb_cmd,
            "ca_total": ca_total,
            "panier_moyen": float(cmd_stats['panier_moyen']),
            "nb_produits_vus": nb_vues,
            "categories_preferees": [c['categorie'] for c in categories_pref if c['categorie']],
            "score_engagement": engagement,
            "score_fidelite": fidelite,
        }

        # Analyse IA
        analyse_ia = self.grok.analyze(
            data,
            context="Analyse le comportement de cet utilisateur Nexio S.A. et donne des recommandations."
        )

        # Sauvegarde en DB
        try:
            cur.execute("""
                INSERT INTO analyses_comportementales
                    (id_user, score_engagement, score_fidelite, categories_preferees,
                     panier_moyen, nb_commandes, analyse_ia, date_analyse)
                VALUES (%s,%s,%s,%s,%s,%s,%s,NOW())
                ON DUPLICATE KEY UPDATE
                    score_engagement=%s, score_fidelite=%s, categories_preferees=%s,
                    panier_moyen=%s, nb_commandes=%s, analyse_ia=%s, date_analyse=NOW()
            """, (
                id_user, engagement, fidelite,
                json.dumps(categories_pref, ensure_ascii=False),
                float(cmd_stats['panier_moyen']), nb_cmd,
                json.dumps(analyse_ia, ensure_ascii=False),
                engagement, fidelite,
                json.dumps(categories_pref, ensure_ascii=False),
                float(cmd_stats['panier_moyen']), nb_cmd,
                json.dumps(analyse_ia, ensure_ascii=False),
            ))
            self.db.commit()
        except Exception as e:
            self.log.warning("Sauvegarde DB ignorée (table manquante) : %s", e)

        elapsed = round(time.time() - start, 2)
        self.log.info("Utilisateur %d analysé en %ss | engagement=%d", id_user, elapsed, engagement)
        return {**data, "analyse_ia": analyse_ia, "duree_s": elapsed}

    def analyser_tous(self) -> list:
        """Lance l'analyse sur tous les clients."""
        cur = self._cursor()
        cur.execute("SELECT id_user FROM users WHERE id_role=(SELECT id_role FROM roles WHERE nom='Client')")
        users = cur.fetchall()
        results = []
        for u in users:
            try:
                r = self.analyser_utilisateur(u['id_user'])
                results.append(r)
            except Exception as e:
                self.log.error("Erreur user %d : %s", u['id_user'], e)
        self.log.info("Analyse comportementale terminée : %d utilisateurs", len(results))
        return results
