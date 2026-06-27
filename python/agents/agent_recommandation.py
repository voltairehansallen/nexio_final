"""
Nexio S.A. — Agent 2 : Recommandation Intelligente
Produits similaires, complémentaires, personnalisés via GrokCloud.
"""

import json, logging, time
import mysql.connector
from api.grok_api import GrokAPI


class AgentRecommandation:
    def __init__(self, db_config: dict):
        self.grok = GrokAPI()
        self.db   = mysql.connector.connect(**db_config)
        self.log  = logging.getLogger("Agent2.Recommandation")

    def _cursor(self):
        if not self.db.is_connected():
            self.db.reconnect(attempts=3, delay=2)
        return self.db.cursor(dictionary=True)

    def recommander_pour_user(self, id_user: int) -> list:
        start = time.time()
        cur   = self._cursor()

        # ── Profil utilisateur (optionnel) ────────────────────
        profil = {}
        try:
            cur.execute("""
                SELECT score_engagement, score_fidelite,
                       categories_preferees, panier_moyen
                FROM analyses_comportementales WHERE id_user=%s
            """, (id_user,))
            profil = cur.fetchone() or {}
        except Exception as e:
            self.log.warning("Profil comportemental indisponible : %s", e)

        # ── Produits disponibles ──────────────────────────────
        try:
            cur.execute("""
                SELECT p.id_produit, p.nom, p.prix,
                       m.nom AS marque, c.nom AS categorie
                FROM produits p
                LEFT JOIN marques m ON p.id_marque=m.id_marque
                LEFT JOIN sous_categories sc ON p.id_sous_categorie=sc.id_sous_categorie
                LEFT JOIN categories c ON sc.id_categorie=c.id_categorie
                WHERE p.statut='Disponible' LIMIT 50
            """)
            produits = cur.fetchall()
        except Exception as e:
            self.log.error("Erreur lecture produits : %s", e)
            return []

        if not produits:
            return []

        # ── Appel GrokCloud ───────────────────────────────────
        try:
            recs = self.grok.recommend(
                user_data={"id_user": id_user, "profil": profil},
                products=produits
            )
        except Exception as e:
            self.log.error("Erreur GrokCloud recommend : %s", e)
            # Fallback : retourner les 5 premiers produits
            recs = [{"id_produit": p["id_produit"],
                     "raison": "Produit populaire",
                     "score": 0.5} for p in produits[:5]]

        # ── Persistance en DB (silencieuse si table absente) ──
        for r in recs:
            try:
                pid = r.get("id_produit")
                if not pid:
                    continue
                cur.execute("""
                    INSERT INTO recommandations(id_user, id_produit, score, raison)
                    VALUES(%s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE score=%s, raison=%s
                """, (id_user, pid,
                      float(r.get("score", 0.5)), str(r.get("raison", "")),
                      float(r.get("score", 0.5)), str(r.get("raison", ""))))
            except Exception:
                pass

        try:
            self.db.commit()
        except Exception:
            pass

        self.log.info("Recommandations user %d : %d produits en %ss",
                      id_user, len(recs), round(time.time()-start, 2))
        return recs

    def produits_similaires(self, id_produit: int) -> list:
        """Produits similaires à un produit donné."""
        cur = self._cursor()

        try:
            cur.execute("""
                SELECT p.*, sc.id_categorie,
                       c.nom AS categorie
                FROM produits p
                LEFT JOIN sous_categories sc ON p.id_sous_categorie=sc.id_sous_categorie
                LEFT JOIN categories c ON sc.id_categorie=c.id_categorie
                WHERE p.id_produit=%s
            """, (id_produit,))
            prod = cur.fetchone()
        except Exception as e:
            self.log.error("Erreur lecture produit %d : %s", id_produit, e)
            return []

        if not prod:
            return []

        # Candidats dans la même catégorie
        try:
            cur.execute("""
                SELECT p.id_produit, p.nom, p.prix, m.nom AS marque
                FROM produits p
                LEFT JOIN marques m ON p.id_marque=m.id_marque
                LEFT JOIN sous_categories sc ON p.id_sous_categorie=sc.id_sous_categorie
                WHERE sc.id_categorie=%s
                  AND p.id_produit != %s
                  AND p.statut='Disponible'
                LIMIT 20
            """, (prod.get("id_categorie"), id_produit))
            candidats = cur.fetchall()
        except Exception as e:
            self.log.error("Erreur candidats similaires : %s", e)
            candidats = []

        if not candidats:
            # Fallback : autres produits disponibles
            try:
                cur.execute("""
                    SELECT p.id_produit, p.nom, p.prix, m.nom AS marque
                    FROM produits p
                    LEFT JOIN marques m ON p.id_marque=m.id_marque
                    WHERE p.id_produit != %s AND p.statut='Disponible'
                    LIMIT 4
                """, (id_produit,))
                candidats = cur.fetchall()
            except Exception:
                return []

        # ── IA pour sélectionner les plus similaires ──────────
        try:
            prompt = (
                f"Produit de référence : {prod['nom']} "
                f"({prod.get('categorie','?')})\n"
                f"Candidats : {json.dumps(candidats, ensure_ascii=False, default=str)}\n"
                "Sélectionne les 4 produits les plus similaires. "
                "Réponds UNIQUEMENT en JSON valide, sans markdown : "
                '[{"id_produit":1,"raison":"..."}]'
            )
            raw = self.grok.generate(prompt, max_tokens=400)
            raw = raw.strip()
            # Nettoyer les backticks markdown si présents
            if raw.startswith("```"):
                raw = raw.split("```")[1]
                if raw.startswith("json"):
                    raw = raw[4:]
                raw = raw.split("```")[0]
            return json.loads(raw.strip())
        except Exception as e:
            self.log.warning("IA similaires fallback : %s", e)
            return candidats[:4]