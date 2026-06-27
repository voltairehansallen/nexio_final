"""
Nexio S.A. — Agent Publicités Intelligentes
Génère des publicités personnalisées selon profil utilisateur.
"""

import json, logging
import mysql.connector
from api.grok_api import GrokAPI


class AgentPublicites:
    def __init__(self, db_config: dict):
        self.grok = GrokAPI()
        self.db   = mysql.connector.connect(**db_config)
        self.log  = logging.getLogger("AgentPublicites")

    def _cursor(self):
        if not self.db.is_connected():
            self.db.reconnect(attempts=3, delay=2)
        return self.db.cursor(dictionary=True)

    def generer_pub_utilisateur(self, id_user: int) -> list:
        """Génère 3 publicités personnalisées pour un utilisateur."""
        cur = self._cursor()

        # Profil IA
        try:
            cur.execute("SELECT * FROM profils_ia WHERE id_user=%s", (id_user,))
            profil = cur.fetchone()
        except Exception:
            profil = None

        # Produits recommandés
        try:
            cur.execute("""
                SELECT p.nom, p.prix, p.image, c.nom AS cat
                FROM recommandations r
                JOIN produits p ON r.id_produit=p.id_produit
                LEFT JOIN sous_categories sc ON p.id_sous_categorie=sc.id_sous_categorie
                LEFT JOIN categories c ON sc.id_categorie=c.id_categorie
                WHERE r.id_user=%s ORDER BY r.score DESC LIMIT 5
            """, (id_user,))
            recos = cur.fetchall()
        except Exception:
            recos = []

        # Produits populaires si pas de recos
        if not recos:
            cur.execute("SELECT p.nom,p.prix,p.image FROM produits p WHERE p.statut='Disponible' ORDER BY p.id_produit DESC LIMIT 5")
            recos = cur.fetchall()

        segment   = profil['segment'] if profil else 'standard'
        cat_pref  = profil['categorie_preferee'] if profil else None
        budget    = float(profil['budget_moyen'] or 30000) if profil else 30000

        prompt = (
            f"Crée 3 publicités pour ce profil client Nexio S.A. :\n"
            f"Segment : {segment}, Catégorie préférée : {cat_pref}, Budget : {budget:,.0f} HTG\n"
            f"Produits disponibles : {json.dumps([{'nom':r['nom'],'prix':r['prix']} for r in recos[:5]], ensure_ascii=False)}\n\n"
            "Réponds UNIQUEMENT en JSON :\n"
            '[{"titre":"...","contenu":"...","produit_nom":"...","lien_relatif":"/vitrine/produit.php?id=1"},...]'
        )
        raw = self.grok.generate(prompt, max_tokens=600)
        try:
            raw = raw.strip().lstrip("```json").lstrip("```").rstrip("```")
            pubs = json.loads(raw)
        except Exception:
            pubs = [
                {"titre":"⚡ Offre du jour","contenu":"Découvrez nos meilleures offres tech.","lien_relatif":"/vitrine/index.php"},
            ]

        # Incrémente impressions si pubs en DB
        try:
            cur.execute("UPDATE publicites SET impressions=impressions+1 WHERE statut='Active' AND (segment_cible=%s OR segment_cible IS NULL) LIMIT 3", (segment,))
            self.db.commit()
        except Exception:
            pass

        return pubs
