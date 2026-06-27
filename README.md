# Nexio S.A. — Plateforme E-Commerce Intelligente v2.0

## Installation (5 étapes)

```
1. Copier nexio_final/ → C:\xampp\htdocs\nexio_final\
2. phpMyAdmin → créer nexio_db → importer nexio_database.sql
3. config/app.php → BASE_URL = 'http://localhost/nexio_final'
4. Créer le dossier : htdocs/nexio_final/assets/uploads/produits/ (vide)
5. http://localhost/nexio_final/
```

## Comptes

| Rôle  | Email           | Mot de passe |
|-------|-----------------|--------------|
| Admin | admin@nexio.com | Admin123!    |
| Client| S'inscrire sur /auth/register.php | — |

---

## Pages vitrine

| Page             | URL                          |
|------------------|------------------------------|
| Accueil          | /vitrine/index.php           |
| Produit          | /vitrine/produit.php?id=X    |
| Panier           | /vitrine/panier.php          |
| Commande         | /vitrine/checkout.php        |
| Mon compte       | /vitrine/compte/index.php    |
| Wishlist         | /vitrine/wishlist.php        |
| Préférences      | /vitrine/preferences.php     |
| Contact          | /vitrine/contact.php         |
| À propos         | /vitrine/about.php           |
| Feedbacks        | /vitrine/feedback.php        |

## Administration

| Module         | URL                              |
|----------------|----------------------------------|
| Dashboard      | /admin/dashboard.php             |
| Produits       | /admin/dashboard.php?page=produits |
| Catégories     | /admin/dashboard.php?page=categories |
| Sous-cats      | /admin/dashboard.php?page=sous_categories |
| Marques        | /admin/dashboard.php?page=marques |
| Fournisseurs   | /admin/dashboard.php?page=fournisseurs |
| Commandes      | /admin/dashboard.php?page=commandes |
| Clients        | /admin/dashboard.php?page=clients |
| Stocks         | /admin/dashboard.php?page=stocks |
| Campagnes      | /admin/dashboard.php?page=campagnes |
| Feedbacks      | /admin/dashboard.php?page=feedbacks |
| Messages       | /admin/dashboard.php?page=messages |
| Rapports       | /admin/dashboard.php?page=rapports |
| Journaux       | /admin/dashboard.php?page=journaux |
| Chat NEX       | /admin/dashboard.php?page=chat |

---

## Agent IA Python (GrokCloud)

```bash
cd nexio_final/python
pip install -r requirements.txt
# Modifier config/ai_config.json → "api_key": "VOTRE_CLE_GROQ"
python main.py
# → http://127.0.0.1:5001
```

Clé gratuite : https://console.groq.com
Modèle : llama-3.3-70b-versatile

## Agents disponibles

| Agent | Route Flask         | Description                    |
|-------|---------------------|--------------------------------|
| 1     | POST /comportement  | Analyse comportementale        |
| 2     | POST /recommander   | Recommandations personnalisées |
| 3     | POST /campagne      | Marketing intelligent          |
| 4     | GET /rapport-ventes | Analyse des ventes             |
| 5     | GET /previsions     | Prévisions & ruptures          |
| 6     | GET /stocks         | Gestion intelligente stocks    |
| 7     | POST /chat          | Chatbot NEX                    |
| 10    | POST /fraude        | Détection fraude               |
| 11    | POST /sentiment     | Analyse sentiments             |

---

## Technologies
PHP 8 · Python 3.12 · MySQL · XAMPP · GrokCloud (llama-3.3-70b-versatile) · Bootstrap 5 · Mobile First

## Nexio S.A.
📍 Delmas, Port-au-Prince, Haïti | 📞 4810-8541 | Lun–Sam 8h–18h
