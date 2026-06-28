"""
Nexio S.A. — GrokCloud API Central Module
Tous les agents passent exclusivement par cette classe.
Modèle : llama-3.3-70b-versatile
"""

import json
import time
import logging
import os
import requests
from pathlib import Path
from typing import Optional


api_key = os.getenv("GROQ_API_KEY") or config["api_key"]
class GrokAPI:
    """
    Classe centrale de communication avec GrokCloud.
    Singleton partagé par tous les agents.
    """
    _instance = None
    _config: dict = {}

    # ── Singleton ─────────────────────────────────────────────
    def __new__(cls):
        if cls._instance is None:
            cls._instance = super().__new__(cls)
            cls._instance._initialized = False
        return cls._instance

    def __init__(self):
        if self._initialized:
            return
        self._initialized = True
        self._load_config()
        self._setup_logging()

    # ── Config ────────────────────────────────────────────────
    def _load_config(self) -> None:
        config_path = Path(__file__).parent.parent / "config" / "ai_config.json"
        with open(config_path, "r", encoding="utf-8") as f:
            self._config = json.load(f)

        # Priorité variables d'environnement
        env_key = os.getenv("GROQ_API_KEY")
        if env_key:
            self._config["api_key"] = env_key

    # ── Logging ───────────────────────────────────────────────
    def _setup_logging(self) -> None:
        log_path = Path(__file__).parent.parent.parent / self._config.get("log_file", "python/logs/grok_api.log")
        log_path.parent.mkdir(parents=True, exist_ok=True)

        logging.basicConfig(
            level=logging.INFO,
            format="%(asctime)s [%(levelname)s] %(name)s — %(message)s",
            handlers=[
                logging.FileHandler(log_path, encoding="utf-8"),
                logging.StreamHandler(),
            ],
        )
        self.logger = logging.getLogger("GrokAPI")

    # ── Fonction principale generate() ────────────────────────
    def generate(
        self,
        prompt: str,
        system: str = "Tu es un assistant intelligent pour Nexio S.A., une plateforme e-commerce de matériel informatique en Haïti. Réponds toujours en français.",
        max_tokens: Optional[int] = None,
        temperature: Optional[float] = None,
    ) -> str:
        """
        Point d'entrée unique pour tous les agents.
        Tous les appels IA passent par cette fonction.
        """
        start = time.time()
        payload = {
            "model":       self._config["model"],
            "messages":    [
                {"role": "system", "content": system},
                {"role": "user",   "content": prompt},
            ],
            "max_tokens":  max_tokens  or self._config["max_tokens"],
            "temperature": temperature or self._config["temperature"],
        }

        headers = {
            "Authorization": f"Bearer {self._config['api_key']}",
            "Content-Type":  "application/json",
        }

        last_error = None
        for attempt in range(1, self._config["retry"] + 1):
            try:
                resp = requests.post(
                    self._config["api_url"],
                    json=payload,
                    headers=headers,
                    timeout=self._config["timeout"],
                )
                resp.raise_for_status()
                result  = resp.json()
                content = result["choices"][0]["message"]["content"]
                elapsed = round(time.time() - start, 2)

                if self._config.get("logs"):
                    self.logger.info(
                        "generate() OK | tentative=%d | tokens=%s | durée=%ss",
                        attempt,
                        result.get("usage", {}).get("total_tokens", "?"),
                        elapsed,
                    )
                return content

            except requests.exceptions.Timeout:
                last_error = "Timeout"
                self.logger.warning("Tentative %d — Timeout", attempt)
            except requests.exceptions.HTTPError as e:
                last_error = str(e)
                self.logger.error("Tentative %d — HTTP %s", attempt, e.response.status_code)
                if e.response.status_code in (401, 403):
                    break  # Pas de retry pour auth errors
            except Exception as e:
                last_error = str(e)
                self.logger.error("Tentative %d — Erreur : %s", attempt, e)

            if attempt < self._config["retry"]:
                time.sleep(self._config["retry_delay"] * attempt)

        self.logger.error("generate() ÉCHEC après %d tentatives : %s", self._config["retry"], last_error)
        return f"[Erreur IA] Service temporairement indisponible. Détail : {last_error}"

    # ── Méthodes spécialisées (utilisent generate()) ──────────
    def analyze(self, data: dict, context: str = "") -> dict:
        """Analyse structurée — retourne un dict JSON."""
        prompt = f"{context}\n\nDonnées à analyser :\n{json.dumps(data, ensure_ascii=False, indent=2)}\n\nRéponds UNIQUEMENT en JSON valide, sans markdown."
        raw = self.generate(prompt, max_tokens=1500)
        try:
            raw = raw.strip()
            if raw.startswith("```"):
                raw = raw.split("```")[1]
                if raw.startswith("json"):
                    raw = raw[4:]
            return json.loads(raw)
        except json.JSONDecodeError:
            return {"raw": raw, "error": "JSON invalide"}

    def recommend(self, user_data: dict, products: list) -> list:
        """Recommandation de produits personnalisée."""
        prompt = (
            f"Utilisateur : {json.dumps(user_data, ensure_ascii=False)}\n"
            f"Produits disponibles : {json.dumps(products[:20], ensure_ascii=False)}\n"
            "Recommande les 5 meilleurs produits pour cet utilisateur. "
            "Réponds UNIQUEMENT en JSON : [{\"id_produit\": X, \"raison\": \"...\", \"score\": 0.95}]"
        )
        raw = self.generate(prompt, max_tokens=600)
        try:
            raw = raw.strip().lstrip("```json").rstrip("```")
            return json.loads(raw)
        except Exception:
            return []

    def generate_campaign(self, canal: str, segment: str, context: str) -> str:
        """Génère un message marketing personnalisé."""
        system = (
            "Tu es un expert en marketing digital pour Nexio S.A., "
            "une entreprise haïtienne de matériel informatique. "
            "Génère des messages percutants et adaptés au contexte haïtien."
        )
        prompt = (
            f"Canal : {canal}\n"
            f"Segment client : {segment}\n"
            f"Contexte : {context}\n"
            "Génère un message marketing en français, max 200 mots, avec un appel à l'action clair."
        )
        return self.generate(prompt, system=system, max_tokens=400)

    def analyze_sentiment(self, text: str) -> dict:
        """Analyse de sentiment sur un texte."""
        prompt = (
            f"Analyse le sentiment de ce texte client :\n\"{text}\"\n"
            "Réponds UNIQUEMENT en JSON : {\"sentiment\": \"positif|neutre|négatif\", \"score\": 0.85, \"résumé\": \"...\"}"
        )
        raw = self.generate(prompt, max_tokens=200)
        try:
            raw = raw.strip().lstrip("```json").rstrip("```")
            return json.loads(raw)
        except Exception:
            return {"sentiment": "neutre", "score": 0.5, "résumé": raw}

    def detect_fraud(self, order_data: dict) -> dict:
        """Détection de fraude sur une commande."""
        prompt = (
            f"Analyse cette commande pour détecter une fraude potentielle :\n"
            f"{json.dumps(order_data, ensure_ascii=False, indent=2)}\n"
            "Réponds UNIQUEMENT en JSON : {\"risque\": \"faible|moyen|élevé\", \"score\": 0.2, \"raisons\": []}"
        )
        raw = self.generate(prompt, max_tokens=300)
        try:
            raw = raw.strip().lstrip("```json").rstrip("```")
            return json.loads(raw)
        except Exception:
            return {"risque": "faible", "score": 0.1, "raisons": []}

    def forecast_sales(self, historique: list) -> dict:
        """Prévision des ventes."""
        prompt = (
            f"Données de ventes historiques (30 derniers jours) :\n"
            f"{json.dumps(historique, ensure_ascii=False)}\n"
            "Prévois les ventes des 7 prochains jours. "
            "Réponds UNIQUEMENT en JSON : {\"previsions\": [{\"date\": \"YYYY-MM-DD\", \"ventes_estimees\": 5, \"ca_estime\": 50000}], \"tendance\": \"hausse|baisse|stable\", \"confiance\": 0.8}"
        )
        raw = self.generate(prompt, max_tokens=600)
        try:
            raw = raw.strip().lstrip("```json").rstrip("```")
            return json.loads(raw)
        except Exception:
            return {"previsions": [], "tendance": "stable", "confiance": 0.5}
