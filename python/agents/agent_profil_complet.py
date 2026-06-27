"""
Nexio S.A. — Agent Profil Complet
Analyse individuelle de chaque client :
centres d'intérêt, score achat, segment, probabilité, recommandations.
"""

import json, logging, time
import mysql.connector
from datetime import datetime
from api.grok_api import GrokAPI


class AgentProfilComplet:
    SEGMENTS = {
        'gamers':       lambda u: u.get('nb_jeux',0)>0 or 'gaming' in str(u.get('cats','')).lower(),
        'entreprises':  lambda u: u.get('nb_cmd',0)>=5 or u.get('ca',0)>200000,
        'étudiants':    lambda u: u.get('panier_moy',0)<30000,
        'fidèles':      lambda u: u.get('nb_cmd',0)>=3,
        'nouveaux':     lambda u: u.get('nb_cmd',0)==0,
        'professionnels':lambda u: u.get('ca',0)>100000 and u.get('nb_cmd',0)>=2,
    }

    def __init__(self, db_config: dict):
        self.grok = GrokAPI()
        self.db   = mysql.connector.connect(**db_config)
        self.log  = logging.getLogger("AgentProfilComplet")

    def _cursor(self):
        if not self.db.is_connected():
            self.db.reconnect(attempts=3, delay=2)
        return self.db.cursor(dictionary=True)

    def analyser_utilisateur(self, id_user: int) -> dict:
        """Crée profil IA complet pour un utilisateur."""
        start = time.time()
        cur   = self._cursor()

        # Historique achats
        cur.execute("""
            SELECT COUNT(*) AS nb_cmd,
                   COALESCE(SUM(montant),0) AS ca,
                   COALESCE(AVG(montant),0) AS panier_moy
            FROM commandes WHERE id_user=%s AND statut!='Annulée'
        """, (id_user,))
        achats = cur.fetchone()

        # Catégories achetées
        cur.execute("""
            SELECT c.nom AS cat, COUNT(*) AS nb
            FROM details_commandes dc
            JOIN commandes cmd ON dc.id_commande=cmd.id_commande
            JOIN produits p ON dc.id_produit=p.id_produit
            LEFT JOIN sous_categories sc ON p.id_sous_categorie=sc.id_sous_categorie
            LEFT JOIN categories c ON sc.id_categorie=c.id_categorie
            WHERE cmd.id_user=%s AND cmd.statut!='Annulée'
            GROUP BY c.nom ORDER BY nb DESC LIMIT 5
        """, (id_user,))
        cats_achat = cur.fetchall()

        # Produits consultés
        cur.execute("""
            SELECT c.nom AS cat, COUNT(*) AS nb
            FROM interactions i
            JOIN produits p ON i.id_produit=p.id_produit
            LEFT JOIN sous_categories sc ON p.id_sous_categorie=sc.id_sous_categorie
            LEFT JOIN categories c ON sc.id_categorie=c.id_categorie
            WHERE i.id_user=%s
            GROUP BY c.nom ORDER BY nb DESC LIMIT 5
        """, (id_user,))
        cats_vues = cur.fetchall()

        # Wishlist
        try:
            cur.execute("""
                SELECT c.nom AS cat FROM wishlist w
                JOIN produits p ON w.id_produit=p.id_produit
                LEFT JOIN sous_categories sc ON p.id_sous_categorie=sc.id_sous_categorie
                LEFT JOIN categories c ON sc.id_categorie=c.id_categorie
                WHERE w.id_user=%s
            """, (id_user,))
            wishlist_cats = [r['cat'] for r in cur.fetchall() if r['cat']]
        except Exception:
            wishlist_cats = []

        # Avis
        try:
            cur.execute("SELECT note, commentaire FROM avis WHERE id_user=%s ORDER BY date_avis DESC LIMIT 5", (id_user,))
            avis = cur.fetchall()
        except Exception:
            avis = []

        nb_cmd   = int(achats['nb_cmd'])
        ca       = float(achats['ca'])
        panier   = float(achats['panier_moy'])
        all_cats = [c['cat'] for c in cats_achat if c['cat']] + [c['cat'] for c in cats_vues if c['cat']]
        cat_pref = cats_achat[0]['cat'] if cats_achat else (cats_vues[0]['cat'] if cats_vues else None)

        # Score achat (0-100)
        score = min(100, int(
            nb_cmd * 15 +
            (ca / 10000) +
            len(all_cats) * 2 +
            (10 if wishlist_cats else 0)
        ))

        # Probabilité achat (0-1)
        prob = min(1.0, round(
            (nb_cmd * 0.15) +
            (0.3 if wishlist_cats else 0) +
            (0.2 if ca > 50000 else 0) +
            (0.1 if len(all_cats) > 3 else 0)
        , 2))

        # Détermination segment
        user_data_for_seg = {
            'nb_cmd': nb_cmd, 'ca': ca, 'panier_moy': panier,
            'cats': ' '.join(all_cats), 'nb_jeux': sum(1 for c in all_cats if 'gaming' in c.lower()),
        }
        segment = 'standard'
        for seg_name, seg_fn in self.SEGMENTS.items():
            try:
                if seg_fn(user_data_for_seg):
                    segment = seg_name
                    break
            except Exception:
                pass

        # Analyse IA centres d'intérêt
        prompt = (
            f"Client Nexio S.A. (Port-au-Prince, Haïti) :\n"
            f"- Commandes : {nb_cmd}, CA total : {ca:,.0f} HTG\n"
            f"- Panier moyen : {panier:,.0f} HTG\n"
            f"- Catégories achetées : {[c['cat'] for c in cats_achat]}\n"
            f"- Produits consultés : {[c['cat'] for c in cats_vues]}\n"
            f"- Wishlist : {wishlist_cats}\n"
            f"- Segment détecté : {segment}\n\n"
            "Génère un profil IA en JSON UNIQUEMENT :\n"
            '{"centres_interet":["..."],"frequence_achat":"hebdomadaire/mensuel/rare","recommandations":["..."]}' 
        )
        ia_result = self.grok.analyze({}, context=prompt)

        profil = {
            'id_user':           id_user,
            'centres_interet':   ia_result.get('centres_interet', all_cats[:5]),
            'score_achat':       score,
            'probabilite_achat': prob,
            'categorie_preferee':cat_pref,
            'budget_moyen':      panier,
            'frequence_achat':   ia_result.get('frequence_achat', 'mensuel' if nb_cmd >= 2 else 'rare'),
            'segment':           segment,
            'recommandations':   ia_result.get('recommandations', []),
            'comportement':      json.dumps({
                'nb_commandes': nb_cmd,
                'ca_total': ca,
                'categories_vues': [c['cat'] for c in cats_vues],
                'wishlist': wishlist_cats,
            }),
            'duree_s':           round(time.time()-start, 2),
        }

        # Persiste en DB
        try:
            cur.execute("""
                INSERT INTO profils_ia(id_user,centres_interet,score_achat,probabilite_achat,
                categorie_preferee,budget_moyen,frequence_achat,segment,recommandations,comportement,derniere_analyse)
                VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,NOW())
                ON DUPLICATE KEY UPDATE
                centres_interet=%s, score_achat=%s, probabilite_achat=%s,
                categorie_preferee=%s, budget_moyen=%s, frequence_achat=%s,
                segment=%s, recommandations=%s, comportement=%s, derniere_analyse=NOW()
            """, (
                id_user,
                json.dumps(profil['centres_interet'], ensure_ascii=False),
                score, prob, cat_pref, panier,
                profil['frequence_achat'], segment,
                json.dumps(profil['recommandations'], ensure_ascii=False),
                profil['comportement'],
                # UPDATE
                json.dumps(profil['centres_interet'], ensure_ascii=False),
                score, prob, cat_pref, panier,
                profil['frequence_achat'], segment,
                json.dumps(profil['recommandations'], ensure_ascii=False),
                profil['comportement'],
            ))
            self.db.commit()
        except Exception as e:
            self.log.warning("Sauvegarde profil ignorée : %s", e)

        self.log.info("Profil complet user %d : segment=%s, score=%d", id_user, segment, score)
        return profil

    def analyser_tous(self) -> list:
        """Analyse tous les clients et retourne liste profils."""
        cur = self._cursor()
        cur.execute("""
            SELECT u.id_user FROM users u
            JOIN roles r ON u.id_role=r.id_role
            WHERE r.nom='Client'
        """)
        users = cur.fetchall()
        results = []
        for u in users:
            try:
                p = self.analyser_utilisateur(u['id_user'])
                results.append(p)
                time.sleep(0.3)  # Rate limiting GrokCloud
            except Exception as e:
                self.log.error("Erreur user %d : %s", u['id_user'], e)
        return results
