"""
Nexio S.A. — Agent 7 : Chatbot IA
Répond aux questions, recherche produits, suit les commandes.
Utilise exclusivement GrokCloud llama-3.3-70b-versatile.
"""

import json, logging, time
import mysql.connector
from api.grok_api import GrokAPI


class AgentChatbot:
    SYSTEM = """Tu es l'assistant virtuel de Nexio S.A., une entreprise haïtienne de matériel informatique.
Tu réponds en français, de façon professionnelle, concise et utile.
Tu peux aider avec : recommandations produits, infos techniques, suivi commandes, support.
Si tu ne sais pas, dis-le honnêtement et propose de contacter le support.
Garde tes réponses courtes (2-4 phrases) sauf si détail demandé.
Nexio S.A. : Delmas, Port-au-Prince | Lun-Sam 8h-18h | MonCash, NatCash, Visa."""

    def __init__(self, db_config: dict):
        self.grok = GrokAPI()
        self.db   = mysql.connector.connect(**db_config)
        self.log  = logging.getLogger("Agent7.Chatbot")

    def _cursor(self):
        if not self.db.is_connected():
            self.db.reconnect(attempts=3, delay=2)
        return self.db.cursor(dictionary=True)

    def _contexte_produits(self) -> str:
        """Charge le catalogue produits pour le contexte."""
        try:
            cur = self._cursor()
            cur.execute("""
                SELECT p.nom, p.prix, p.quantite, c.nom AS categorie, m.nom AS marque
                FROM produits p
                LEFT JOIN marques m ON p.id_marque=m.id_marque
                LEFT JOIN sous_categories sc ON p.id_sous_categorie=sc.id_sous_categorie
                LEFT JOIN categories c ON sc.id_categorie=c.id_categorie
                WHERE p.statut='Disponible' LIMIT 20
            """)
            produits = cur.fetchall()
            return "\n".join([
                f"- {p['nom']} ({p['categorie']}) : {p['prix']:,.0f} HTG — stock: {p['quantite']}"
                for p in produits
            ])
        except Exception:
            return "Catalogue non disponible."

    def repondre(self, message: str, session_id: str, id_user: int = None) -> str:
        """Génère une réponse au message client."""
        start = time.time()

        # Historique session (5 derniers messages)
        try:
            cur = self._cursor()
            cur.execute("""
                SELECT role, contenu FROM chat_messages
                WHERE session_id=%s ORDER BY date_envoi DESC LIMIT 5
            """, (session_id,))
            historique = list(reversed(cur.fetchall()))
        except Exception:
            historique = []

        # Contexte enrichi
        catalogue = self._contexte_produits()
        system    = f"{self.SYSTEM}\n\nProduits disponibles :\n{catalogue}"

        # Construction du prompt avec historique
        conv = "\n".join([
            f"{'Client' if h['role']=='user' else 'Assistant'} : {h['contenu']}"
            for h in historique
        ])
        prompt = f"{conv}\nClient : {message}\nAssistant :"

        reponse = self.grok.generate(prompt, system=system, max_tokens=400)

        # Sauvegarde
        try:
            cur.execute(
                "INSERT INTO chat_messages(session_id,id_user,role,contenu) VALUES(%s,%s,'user',%s)",
                (session_id, id_user, message)
            )
            cur.execute(
                "INSERT INTO chat_messages(session_id,id_user,role,contenu) VALUES(%s,%s,'assistant',%s)",
                (session_id, id_user, reponse)
            )
            self.db.commit()
        except Exception as e:
            self.log.warning("Sauvegarde chat ignorée : %s", e)

        self.log.info("Chat répondu en %ss | session=%s", round(time.time()-start,2), session_id)
        return reponse

    def suivre_commande(self, id_commande: int, id_user: int) -> str:
        """Retourne le statut d'une commande."""
        try:
            cur = self._cursor()
            cur.execute("""
                SELECT c.id_commande, c.statut, c.montant, c.date_commande,
                       COUNT(dc.id_detail) AS nb_articles
                FROM commandes c
                LEFT JOIN details_commandes dc ON c.id_commande=dc.id_commande
                WHERE c.id_commande=%s AND c.id_user=%s
                GROUP BY c.id_commande
            """, (id_commande, id_user))
            cmd = cur.fetchone()
            if not cmd:
                return "Commande introuvable ou accès non autorisé."
            return (
                f"Votre commande #CMD-{str(id_commande).zfill(5)} : "
                f"statut **{cmd['statut']}**, {cmd['nb_articles']} article(s), "
                f"montant {cmd['montant']:,.0f} HTG. "
                f"Passée le {cmd['date_commande'].strftime('%d/%m/%Y') if cmd['date_commande'] else '?'}."
            )
        except Exception as e:
            return f"Impossible de récupérer la commande : {e}"
