"""
Nexio S.A. — Serveur Flask Principal
Expose tous les agents IA via REST API sur le port 5001.
Usage : python main.py
"""

import json
import os
import sys
sys.path.insert(0, os.path.dirname(__file__))

# ── Chargement .env ──────────────────────────────────────────
_env_path = os.path.join(os.path.dirname(__file__), '..', '.env')
if os.path.exists(_env_path):
    try:
        from dotenv import load_dotenv
        load_dotenv(_env_path)
    except ImportError:
        with open(_env_path) as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith('#') and '=' in line:
                    k, v = line.split('=', 1)
                    os.environ.setdefault(k.strip(), v.strip())

from flask import Flask, request, jsonify

app = Flask(__name__)

# ── CORS — autorise les requêtes depuis localhost ────────────
@app.after_request
def add_cors(response):
    response.headers["Access-Control-Allow-Origin"]  = "*"
    response.headers["Access-Control-Allow-Headers"] = "Content-Type, Authorization"
    response.headers["Access-Control-Allow-Methods"] = "GET, POST, OPTIONS"
    return response

@app.route("/", defaults={"path": ""}, methods=["OPTIONS"])
@app.route("/<path:path>", methods=["OPTIONS"])
def options_handler(path):
    return jsonify({}), 200

# ── Config DB (lit .env) ─────────────────────────────────────
DB_CONFIG = {
    "host":     os.getenv("MYSQL_HOST",     "localhost"),
    "database": os.getenv("MYSQL_DATABASE", "nexio_db"),
    "user":     os.getenv("MYSQL_USER",     "root"),
    "password": os.getenv("MYSQL_PASSWORD", ""),
    "charset":  "utf8mb4",
}

# ── Singleton agents ─────────────────────────────────────────
def get_agent(cls):
    if not hasattr(app, "_agents"):
        app._agents = {}
    key = cls.__name__
    if key not in app._agents:
        app._agents[key] = cls(DB_CONFIG)
    return app._agents[key]

# ════════════════════════════════════════════════════════════
# ROUTES
# ════════════════════════════════════════════════════════════

@app.route("/ping", methods=["GET"])
def ping():
    return jsonify({"status": "ok", "service": "Nexio IA", "model": "llama-3.3-70b-versatile"})

# ── Statut complet de tous les agents ───────────────────────
@app.route("/agents-status", methods=["GET"])
def agents_status():
    agents_list = [
        {"id": 1,  "nom": "Analyse Comportementale", "route": "/comportement"},
        {"id": 2,  "nom": "Recommandation",           "route": "/recommander"},
        {"id": 3,  "nom": "Marketing",                "route": "/campagne"},
        {"id": 4,  "nom": "Analyse Ventes",           "route": "/rapport-ventes"},
        {"id": 5,  "nom": "Prévisions",               "route": "/previsions"},
        {"id": 6,  "nom": "Stock Intelligence",       "route": "/stocks"},
        {"id": 7,  "nom": "Chatbot NEX",              "route": "/chat"},
        {"id": 10, "nom": "Détection Fraude",         "route": "/fraude"},
        {"id": 11, "nom": "Analyse Sentiments",       "route": "/sentiment"},
        {"id": 12, "nom": "Profil Complet",           "route": "/profil-complet"},
        {"id": 13, "nom": "Campagne IA",              "route": "/generer-campagne"},
        {"id": 14, "nom": "Publicités",               "route": "/publicites"},
        {"id": 15, "nom": "Envoi Multi-canal",        "route": "/envoyer-campagne"},
    ]
    from api.grok_api import GrokAPI
    grok_ok = False
    try:
        g = GrokAPI()
        key = g._config.get("api_key", "")
        grok_ok = bool(key and key != "VOTRE_CLE_GROQ_ICI")
    except Exception:
        pass
    return jsonify({
        "status":     "ok",
        "model":      "llama-3.3-70b-versatile",
        "grok_ready": grok_ok,
        "agents":     agents_list,
        "nb_agents":  len(agents_list),
    })

# ── Agent 7 — Chatbot NEX ───────────────────────────────────
@app.route("/chat", methods=["POST"])
def chat():
    from agents.agent_chatbot import AgentChatbot
    d = request.get_json() or {}
    msg     = d.get("message", "")
    session = d.get("session_id", "default")
    uid     = d.get("id_user")
    if not msg:
        return jsonify({"error": "message requis"}), 400
    return jsonify({"reply": get_agent(AgentChatbot).repondre(msg, session, uid), "status": "ok"})

# ── Agent 2 — Recommandations ────────────────────────────────
@app.route("/recommander", methods=["POST"])
def recommander():
    from agents.agent_recommandation import AgentRecommandation
    try:
        d = request.get_json() or {}
        id_user = d.get("id_user")
        if not id_user:
            return jsonify({"error": "id_user requis"}), 400
        recs = get_agent(AgentRecommandation).recommander_pour_user(int(id_user))
        return jsonify({"recommandations": recs, "status": "ok"})
    except Exception as e:
        import traceback
        return jsonify({"error": str(e), "trace": traceback.format_exc()[-500:]}), 500

@app.route("/similaires", methods=["POST"])
def similaires():
    from agents.agent_recommandation import AgentRecommandation
    try:
        d = request.get_json() or {}
        id_produit = d.get("id_produit")
        if not id_produit:
            return jsonify({"error": "id_produit requis"}), 400
        sims = get_agent(AgentRecommandation).produits_similaires(int(id_produit))
        return jsonify({"similaires": sims})
    except Exception as e:
        return jsonify({"error": str(e)}), 500

# ── Agent 3 — Marketing ──────────────────────────────────────
@app.route("/campagne", methods=["POST"])
def campagne():
    from agents.agent_marketing import AgentMarketing
    try:
        d = request.get_json() or {}
        id_camp = d.get("id_campagne")
        if not id_camp:
            return jsonify({"error": "id_campagne requis"}), 400
        return jsonify(get_agent(AgentMarketing).generer_campagne(int(id_camp)))
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route("/paniers-abandonnes", methods=["GET"])
def paniers_abandonnes():
    from agents.agent_marketing import AgentMarketing
    try:
        return jsonify({"paniers": get_agent(AgentMarketing).detecter_paniers_abandonnes()})
    except Exception as e:
        return jsonify({"error": str(e)}), 500

# ── Agent 4 — Ventes ─────────────────────────────────────────
@app.route("/rapport-ventes", methods=["GET"])
def rapport_ventes():
    from agents.agent_ventes import AgentVentes
    try:
        jours = int(request.args.get("jours", 30))
        return jsonify(get_agent(AgentVentes).rapport_complet(jours))
    except Exception as e:
        return jsonify({"error": str(e)}), 500

# ── Agent 5 — Prévisions ─────────────────────────────────────
@app.route("/previsions", methods=["GET"])
def previsions():
    from agents.agent_prevision import AgentPrevision
    try:
        agent = get_agent(AgentPrevision)
        return jsonify({"ventes": agent.prevoir_ventes(), "ruptures": agent.prevoir_ruptures()})
    except Exception as e:
        return jsonify({"error": str(e)}), 500

# ── Agent 6 — Stock ──────────────────────────────────────────
@app.route("/stocks", methods=["GET"])
def stocks():
    from agents.agent_stock import AgentStock
    try:
        return jsonify(get_agent(AgentStock).analyser_stocks())
    except Exception as e:
        return jsonify({"error": str(e)}), 500

# ── Agent 1 — Comportemental ─────────────────────────────────
@app.route("/comportement", methods=["POST"])
def comportement():
    from agents.agent_comportemental import AgentComportemental
    try:
        d = request.get_json() or {}
        id_user = d.get("id_user")
        agent   = get_agent(AgentComportemental)
        if id_user:
            return jsonify(agent.analyser_utilisateur(int(id_user)))
        return jsonify({"resultats": agent.analyser_tous()})
    except Exception as e:
        return jsonify({"error": str(e)}), 500

# ── Agent 10 — Fraude ────────────────────────────────────────
@app.route("/fraude", methods=["POST"])
def fraude():
    from agents.agent_fraude import AgentFraude
    try:
        d = request.get_json() or {}
        id_commande = d.get("id_commande")
        agent = get_agent(AgentFraude)
        if id_commande:
            return jsonify(agent.analyser_commande(int(id_commande)))
        return jsonify({"suspicieuses": agent.scanner_commandes_recentes()})
    except Exception as e:
        return jsonify({"error": str(e)}), 500

# ── Agent 11 — Sentiment ─────────────────────────────────────
@app.route("/sentiment", methods=["POST"])
def sentiment():
    from agents.agent_sentiment import AgentSentiment
    try:
        d = request.get_json() or {}
        agent = get_agent(AgentSentiment)
        id_avis = d.get("id_avis")
        if id_avis:
            return jsonify(agent.analyser_avis(int(id_avis)))
        return jsonify(agent.rapport_satisfaction())
    except Exception as e:
        return jsonify({"error": str(e)}), 500

# ── Agent 12 — Profil Complet ────────────────────────────────
@app.route("/profil-complet", methods=["POST"])
def profil_complet():
    from agents.agent_profil_complet import AgentProfilComplet
    try:
        d = request.get_json() or {}
        agent   = get_agent(AgentProfilComplet)
        id_user = d.get("id_user")
        if id_user:
            return jsonify(agent.analyser_utilisateur(int(id_user)))
        return jsonify({"resultats": agent.analyser_tous()})
    except Exception as e:
        return jsonify({"error": str(e)}), 500

# ── Agent 13 — Campagne IA ───────────────────────────────────
@app.route("/generer-campagne", methods=["POST"])
def generer_campagne_ia():
    from agents.agent_campagne_ia import AgentCampagneIA
    try:
        d = request.get_json() or {}
        return jsonify(get_agent(AgentCampagneIA).generer_campagne(
            nom       = d.get("nom", "Campagne Nexio"),
            canal     = d.get("canal", "Email"),
            type_camp = d.get("type", "globale"),
            segment   = d.get("segment", ""),
            id_user   = int(d["id_user"]) if d.get("id_user") else None,
        ))
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route("/campagnes-segments", methods=["GET"])
def campagnes_segments():
    from agents.agent_campagne_ia import AgentCampagneIA
    try:
        return jsonify({"campagnes": get_agent(AgentCampagneIA).generer_campagnes_segments()})
    except Exception as e:
        return jsonify({"error": str(e)}), 500

# ── Agent 14 — Publicités ────────────────────────────────────
@app.route("/publicites", methods=["POST"])
def publicites():
    from agents.agent_publicites import AgentPublicites
    try:
        d = request.get_json() or {}
        id_user = d.get("id_user")
        if not id_user:
            return jsonify({"error": "id_user requis"}), 400
        return jsonify({"publicites": get_agent(AgentPublicites).generer_pub_utilisateur(int(id_user))})
    except Exception as e:
        return jsonify({"error": str(e)}), 500

# ── Agent 15 — Envoi Multi-canal ────────────────────────────
@app.route("/envoyer-campagne", methods=["POST"])
def envoyer_campagne_route():
    from agents.agent_envoi import AgentEnvoi
    try:
        d = request.get_json() or {}
        id_campagne = d.get("id_campagne")
        if not id_campagne:
            return jsonify({"error": "id_campagne requis"}), 400
        return jsonify(get_agent(AgentEnvoi).envoyer_campagne(int(id_campagne)))
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route("/test-envoi", methods=["POST"])
def test_envoi():
    from agents.agent_envoi import AgentEnvoi
    try:
        d = request.get_json() or {}
        return jsonify(get_agent(AgentEnvoi).envoyer_test(
            email   = d.get("email", ""),
            message = d.get("message", "Test Nexio IA"),
            canal   = d.get("canal", "Email"),
        ))
    except Exception as e:
        return jsonify({"error": str(e)}), 500

# ── Log IA ───────────────────────────────────────────────────
@app.route("/log-ia", methods=["POST"])
def log_ia():
    d = request.get_json() or {}
    try:
        import mysql.connector
        db = mysql.connector.connect(**DB_CONFIG)
        cur = db.cursor()
        cur.execute(
            "INSERT INTO log_analyses_ia(agent,action,statut,duree_ms,tokens_utilises,detail) VALUES(%s,%s,%s,%s,%s,%s)",
            (d.get("agent",""), d.get("action",""), d.get("statut","succès"),
             int(d.get("duree_ms",0)), int(d.get("tokens",0)), d.get("detail",""))
        )
        db.commit(); db.close()
        return jsonify({"ok": True})
    except Exception as e:
        return jsonify({"ok": False, "error": str(e)})

# ════════════════════════════════════════════════════════════
# LANCEMENT
# ════════════════════════════════════════════════════════════

if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5001))
    app.run(host="0.0.0.0", port=port)
    
    
