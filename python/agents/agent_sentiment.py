"""Nexio S.A. — Agent 11 : Analyse des Sentiments."""
import json, logging
import mysql.connector
from api.grok_api import GrokAPI


class AgentSentiment:
    def __init__(self, db_config: dict):
        self.grok = GrokAPI()
        self.db   = mysql.connector.connect(**db_config)
        self.log  = logging.getLogger("Agent11.Sentiment")

    def _cursor(self):
        if not self.db.is_connected():
            self.db.reconnect(attempts=3, delay=2)
        return self.db.cursor(dictionary=True)

    def analyser_avis(self, id_avis: int) -> dict:
        cur = self._cursor()
        cur.execute("SELECT * FROM avis WHERE id_avis=%s", (id_avis,))
        avis = cur.fetchone()
        if not avis:
            return {"erreur": "Avis introuvable"}
        texte = avis.get('commentaire', '') or ''
        result = self.grok.analyze_sentiment(texte)
        try:
            cur.execute(
                "UPDATE avis SET sentiment=%s, sentiment_score=%s WHERE id_avis=%s",
                (result.get('sentiment'), result.get('score'), id_avis)
            )
            self.db.commit()
        except Exception:
            pass
        return result

    def rapport_satisfaction(self) -> dict:
        cur = self._cursor()
        cur.execute("SELECT commentaire FROM avis WHERE commentaire IS NOT NULL AND commentaire!='' ORDER BY date_avis DESC LIMIT 50")
        avis = cur.fetchall()
        sentiments = {"positif": 0, "neutre": 0, "négatif": 0}
        for a in avis:
            r = self.grok.analyze_sentiment(a['commentaire'])
            s = r.get('sentiment', 'neutre')
            if s in sentiments:
                sentiments[s] += 1
        total = sum(sentiments.values()) or 1
        return {
            "total_avis": total,
            "sentiments": sentiments,
            "satisfaction_pct": round(sentiments['positif'] / total * 100, 1)
        }
