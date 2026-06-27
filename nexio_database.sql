-- ============================================================
-- NEXIO S.A. — Base de données complète
-- Importer dans phpMyAdmin → nexio_db
-- Admin : admin@nexio.com / Admin123!
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;


CREATE DATABASE IF NOT EXISTS `nexio_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nexio_db`;

-- Suppression des tables existantes
DROP TABLE IF EXISTS `journal_activites`;
DROP TABLE IF EXISTS `avis`;
DROP TABLE IF EXISTS `panier`;
DROP TABLE IF EXISTS `chat_messages`;
DROP TABLE IF EXISTS `paiements`;
DROP TABLE IF EXISTS `details_commandes`;
DROP TABLE IF EXISTS `commandes`;
DROP TABLE IF EXISTS `produits`;
DROP TABLE IF EXISTS `marques`;
DROP TABLE IF EXISTS `sous_categories`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

CREATE DATABASE IF NOT EXISTS `nexio_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nexio_db`;

-- ── ROLES ────────────────────────────────────────────────────
CREATE TABLE `roles` (
  `id_role` INT AUTO_INCREMENT PRIMARY KEY,
  `nom` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO `roles` (`nom`) VALUES ('Administrateur'), ('Client');

-- ── USERS ────────────────────────────────────────────────────
CREATE TABLE `users` (
  `id_user` INT AUTO_INCREMENT PRIMARY KEY,
  `id_role` INT NOT NULL DEFAULT 2,
  `nom` VARCHAR(100) NOT NULL,
  `prenom` VARCHAR(100) NOT NULL DEFAULT '',
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `mot_de_passe` VARCHAR(255) NOT NULL,
  `telephone` VARCHAR(30) DEFAULT NULL,
  `adresse` TEXT DEFAULT NULL,
  `statut` ENUM('Actif','Inactif') NOT NULL DEFAULT 'Actif',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_role`) REFERENCES `roles`(`id_role`)
) ENGINE=InnoDB;

-- Admin : Admin123!
INSERT INTO `users` (`id_role`,`nom`,`prenom`,`email`,`mot_de_passe`,`statut`) VALUES
(1, 'Admin', 'Nexio', 'admin@nexio.com', '$2b$12$6F370SrxD1ro5gH5nLx0ouMZSstXX6VpIPLGp19xExBgR5pN/48ny', 'Actif');

-- ── CATEGORIES ───────────────────────────────────────────────
CREATE TABLE `categories` (
  `id_categorie` INT AUTO_INCREMENT PRIMARY KEY,
  `nom` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `icone` VARCHAR(50) DEFAULT 'bi-grid'
) ENGINE=InnoDB;

INSERT INTO `categories` (`nom`,`description`,`icone`) VALUES
('Ordinateurs',    'PC de bureau et laptops',              'bi-pc-display'),
('Réseau',         'Routeurs, switches et câbles',          'bi-wifi'),
('Stockage',       'SSD, HDD et clés USB',                 'bi-device-hdd'),
('Gaming',         'Matériel pour joueurs',                 'bi-controller'),
('Périphériques',  'Claviers, souris et écrans',           'bi-keyboard'),
('Sécurité IT',   'Caméras, firewall et antivirus',        'bi-shield-lock'),
('Téléphonie',     'Smartphones et accessoires',            'bi-phone'),
('Bureautique',    'Imprimantes, scanners et fournitures',  'bi-printer');

-- ── SOUS-CATEGORIES ───────────────────────────────────────────
CREATE TABLE `sous_categories` (
  `id_sous_categorie` INT AUTO_INCREMENT PRIMARY KEY,
  `id_categorie` INT NOT NULL,
  `nom` VARCHAR(100) NOT NULL,
  FOREIGN KEY (`id_categorie`) REFERENCES `categories`(`id_categorie`) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO `sous_categories` (`id_categorie`,`nom`) VALUES
(1,'Laptops'),(1,'PC Bureau'),(1,'All-in-One'),
(2,'Routeurs WiFi'),(2,'Switches'),(2,'Câbles réseau'),
(3,'SSD'),(3,'HDD'),(3,'Clés USB'),
(4,'Cartes graphiques'),(4,'Manettes'),(4,'Casques gaming'),
(5,'Claviers'),(5,'Souris'),(5,'Écrans'),
(6,'Caméras IP'),(6,'Antivirus'),(6,'Firewall'),
(7,'Smartphones'),(7,'Accessoires mobiles'),
(8,'Imprimantes'),(8,'Scanners');

-- ── MARQUES ──────────────────────────────────────────────────
CREATE TABLE `marques` (
  `id_marque` INT AUTO_INCREMENT PRIMARY KEY,
  `nom` VARCHAR(100) NOT NULL,
  `pays` VARCHAR(50) DEFAULT NULL
) ENGINE=InnoDB;

INSERT INTO `marques` (`nom`,`pays`) VALUES
('Dell','USA'),('HP','USA'),('Lenovo','Chine'),
('Apple','USA'),('Samsung','Corée du Sud'),('ASUS','Taiwan'),
('TP-Link','Chine'),('Logitech','Suisse'),('Kingston','USA'),
('Seagate','USA'),('Western Digital','USA'),('Nvidia','USA');

-- ── PRODUITS ─────────────────────────────────────────────────
CREATE TABLE `produits` (
  `id_produit` INT AUTO_INCREMENT PRIMARY KEY,
  `id_sous_categorie` INT DEFAULT NULL,
  `id_marque` INT DEFAULT NULL,
  `nom` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `prix` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cout` DECIMAL(12,2) DEFAULT NULL,
  `quantite` INT NOT NULL DEFAULT 0,
  `seuil_alerte` INT NOT NULL DEFAULT 5,
  `image` VARCHAR(500) DEFAULT NULL,
  `garantie` VARCHAR(50) DEFAULT NULL,
  `statut` ENUM('Disponible','Rupture') NOT NULL DEFAULT 'Disponible',
  `date_ajout` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_sous_categorie`) REFERENCES `sous_categories`(`id_sous_categorie`) ON DELETE SET NULL,
  FOREIGN KEY (`id_marque`) REFERENCES `marques`(`id_marque`) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO `produits` (`id_sous_categorie`,`id_marque`,`nom`,`description`,`prix`,`cout`,`quantite`,`image`,`garantie`,`statut`) VALUES
(1,2,'HP EliteBook 840 G9','Laptop professionnel 14", Intel Core i7, 16GB RAM, 512GB SSD, Windows 11 Pro',85000.00,70000.00,8,'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=400&q=80','1 an','Disponible'),
(1,1,'Dell Inspiron 15 3520','Laptop 15.6", Intel Core i5, 8GB RAM, 256GB SSD, parfait pour étudiants',62000.00,50000.00,12,'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?w=400&q=80','1 an','Disponible'),
(1,3,'Lenovo ThinkPad X1 Carbon','Ultrabook business 14", i7 12th gen, 16GB RAM, 1TB SSD, léger 1.12kg',110000.00,90000.00,5,'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?w=400&q=80','2 ans','Disponible'),
(2,1,'Dell OptiPlex 7090','PC bureau compact, Intel Core i5, 8GB RAM, 500GB HDD, Windows 11',45000.00,36000.00,10,'https://images.unsplash.com/photo-1593640408182-31c228f16b50?w=400&q=80','1 an','Disponible'),
(4,7,'TP-Link Archer AX73','Routeur WiFi 6 double bande, 5400 Mbps, 6 antennes, idéal bureau/maison',18500.00,14000.00,20,'https://images.unsplash.com/photo-1606904825846-647eb07f5be2?w=400&q=80','1 an','Disponible'),
(4,7,'TP-Link TL-SG108','Switch 8 ports Gigabit, non manageable, plug & play, boîtier métal',8500.00,6500.00,25,'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&q=80','1 an','Disponible'),
(7,9,'Kingston SSD A400 480GB','SSD SATA 2.5", vitesse lecture 500MB/s, améliore drastiquement les performances',9500.00,7000.00,30,'https://images.unsplash.com/photo-1597225244516-7b8a7ecfd28f?w=400&q=80','3 ans','Disponible'),
(8,10,'Seagate Barracuda 2TB','Disque dur interne 3.5", 7200 RPM, SATA 6Gb/s, cache 256MB',12000.00,9500.00,18,'https://images.unsplash.com/photo-1531492746076-161ca9bcad58?w=400&q=80','2 ans','Disponible'),
(10,12,'ASUS ROG Strix RTX 3060','Carte graphique 12GB GDDR6, HDMI 2.1, idéale gaming et design 3D',75000.00,60000.00,6,'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?w=400&q=80','3 ans','Disponible'),
(13,8,'Logitech MX Keys','Clavier sans fil premium, rétroéclairé, compatible multi-appareils, frappe silencieuse',15000.00,11000.00,15,'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=400&q=80','1 an','Disponible'),
(14,8,'Logitech MX Master 3','Souris sans fil ergonomique, 8000 DPI, molette MagSpeed, compatible tous OS',14000.00,10500.00,20,'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=400&q=80','1 an','Disponible'),
(16,NULL,'Caméra IP Dahua 4MP','Caméra de surveillance extérieure, vision nocturne 30m, résolution 4MP, IP67',22000.00,17000.00,12,'https://images.unsplash.com/photo-1557597774-9d273605dfa9?w=400&q=80','1 an','Disponible'),
(19,5,'Samsung Galaxy A54','Smartphone 6.4", 128GB, 8GB RAM, triple caméra 50MP, batterie 5000mAh',42000.00,34000.00,14,'https://images.unsplash.com/photo-1610945265064-0e34e5519bbf?w=400&q=80','1 an','Disponible'),
(15,5,'Samsung 27" Monitor S27C310','Écran 27", Full HD 1080p, IPS, 75Hz, Eye Care, HDMI+VGA',32000.00,25000.00,9,'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?w=400&q=80','1 an','Disponible'),
(9,9,'Kingston USB 64GB DataTraveler','Clé USB 3.2 Gen 1, vitesse lecture 100MB/s, boîtier métal durable',2500.00,1800.00,50,'https://images.unsplash.com/photo-1587825140708-dfaf72ae4b04?w=400&q=80','5 ans','Disponible'),
(21,2,'HP LaserJet Pro M15w','Imprimante laser monochrome WiFi, 18ppm, compacte, toner inclus',28000.00,22000.00,7,'https://images.unsplash.com/photo-1612815154858-60aa4c59eaa6?w=400&q=80','1 an','Disponible');

-- ── COMMANDES ────────────────────────────────────────────────
CREATE TABLE `commandes` (
  `id_commande` INT AUTO_INCREMENT PRIMARY KEY,
  `id_user` INT NOT NULL,
  `montant` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `statut` ENUM('En attente','Confirmée','Expédiée','Livrée','Annulée') NOT NULL DEFAULT 'En attente',
  `adresse_livraison` TEXT DEFAULT NULL,
  `date_commande` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── DETAILS COMMANDES ─────────────────────────────────────────
CREATE TABLE `details_commandes` (
  `id_detail` INT AUTO_INCREMENT PRIMARY KEY,
  `id_commande` INT NOT NULL,
  `id_produit` INT NOT NULL,
  `quantite` INT NOT NULL DEFAULT 1,
  `prix` DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (`id_commande`) REFERENCES `commandes`(`id_commande`) ON DELETE CASCADE,
  FOREIGN KEY (`id_produit`) REFERENCES `produits`(`id_produit`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── PAIEMENTS ────────────────────────────────────────────────
CREATE TABLE `paiements` (
  `id_paiement` INT AUTO_INCREMENT PRIMARY KEY,
  `id_commande` INT NOT NULL,
  `montant` DECIMAL(12,2) NOT NULL,
  `methode` ENUM('MonCash','NatCash','Visa','Espèces') NOT NULL DEFAULT 'MonCash',
  `statut` ENUM('En attente','Payé','Échoué') NOT NULL DEFAULT 'En attente',
  `date_paiement` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_commande`) REFERENCES `commandes`(`id_commande`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── MESSAGES CHATBOT ─────────────────────────────────────────
CREATE TABLE `chat_messages` (
  `id_msg` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` VARCHAR(100) NOT NULL,
  `id_user` INT DEFAULT NULL,
  `role` ENUM('user','assistant') NOT NULL,
  `contenu` TEXT NOT NULL,
  `date_envoi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_session` (`session_id`),
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── PANIER PERSISTANT ─────────────────────────────────────────
CREATE TABLE `panier` (
  `id_panier` INT AUTO_INCREMENT PRIMARY KEY,
  `id_user` INT NOT NULL,
  `id_produit` INT NOT NULL,
  `quantite` INT NOT NULL DEFAULT 1,
  `date_ajout` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_user_prod` (`id_user`,`id_produit`),
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`) ON DELETE CASCADE,
  FOREIGN KEY (`id_produit`) REFERENCES `produits`(`id_produit`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── AVIS PRODUITS ─────────────────────────────────────────────
CREATE TABLE `avis` (
  `id_avis` INT AUTO_INCREMENT PRIMARY KEY,
  `id_produit` INT NOT NULL,
  `id_user` INT NOT NULL,
  `note` TINYINT NOT NULL DEFAULT 5 CHECK (`note` BETWEEN 1 AND 5),
  `commentaire` TEXT DEFAULT NULL,
  `date_avis` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_produit`) REFERENCES `produits`(`id_produit`) ON DELETE CASCADE,
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── LOG ACTIVITES ADMIN ───────────────────────────────────────
CREATE TABLE `journal_activites` (
  `id_log` INT AUTO_INCREMENT PRIMARY KEY,
  `id_user` INT DEFAULT NULL,
  `action` VARCHAR(200) NOT NULL,
  `ip` VARCHAR(50) DEFAULT NULL,
  `date_action` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- Note : mot de passe admin = Admin123!
-- Hash bcrypt généré avec password_hash('Admin123!', PASSWORD_BCRYPT, ['cost'=>12])
-- ============================================================

SET FOREIGN_KEY_CHECKS = 1;

-- ── FEEDBACKS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `feedbacks` (
  `id_feedback` INT AUTO_INCREMENT PRIMARY KEY,
  `id_user` INT DEFAULT NULL,
  `nom` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `note` TINYINT NOT NULL DEFAULT 5,
  `type_feedback` ENUM('Expérience','Problème','Suggestion','Commentaire') DEFAULT 'Expérience',
  `commentaire` TEXT,
  `statut` ENUM('En attente','Approuvé','Rejeté') NOT NULL DEFAULT 'En attente',
  `sentiment` VARCHAR(20) DEFAULT NULL,
  `sentiment_score` DECIMAL(4,2) DEFAULT NULL,
  `date_feedback` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── WISHLIST ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `wishlist` (
  `id_wish` INT AUTO_INCREMENT PRIMARY KEY,
  `id_user` INT NOT NULL,
  `id_produit` INT NOT NULL,
  `date_ajout` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_wish` (`id_user`,`id_produit`),
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`) ON DELETE CASCADE,
  FOREIGN KEY (`id_produit`) REFERENCES `produits`(`id_produit`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── MESSAGES CONTACT ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `messages_contact` (
  `id_contact` INT AUTO_INCREMENT PRIMARY KEY,
  `nom` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `sujet` VARCHAR(200) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `statut` ENUM('Non lu','Lu','Répondu') NOT NULL DEFAULT 'Non lu',
  `date_envoi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── INTERACTIONS PRODUITS ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `interactions` (
  `id_interaction` INT AUTO_INCREMENT PRIMARY KEY,
  `id_user` INT DEFAULT NULL,
  `id_produit` INT NOT NULL,
  `action` VARCHAR(50) NOT NULL DEFAULT 'view',
  `page` VARCHAR(100) DEFAULT NULL,
  `date_interaction` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`) ON DELETE SET NULL,
  FOREIGN KEY (`id_produit`) REFERENCES `produits`(`id_produit`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── ANALYSES COMPORTEMENTALES ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `analyses_comportementales` (
  `id_analyse` INT AUTO_INCREMENT PRIMARY KEY,
  `id_user` INT NOT NULL UNIQUE,
  `score_engagement` INT DEFAULT 0,
  `score_fidelite` INT DEFAULT 0,
  `categories_preferees` TEXT DEFAULT NULL,
  `panier_moyen` DECIMAL(12,2) DEFAULT 0,
  `nb_commandes` INT DEFAULT 0,
  `analyse_ia` TEXT DEFAULT NULL,
  `date_analyse` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── RECOMMANDATIONS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `recommandations` (
  `id_reco` INT AUTO_INCREMENT PRIMARY KEY,
  `id_user` INT NOT NULL,
  `id_produit` INT NOT NULL,
  `score` DECIMAL(4,2) DEFAULT 0.5,
  `raison` VARCHAR(255) DEFAULT NULL,
  `date_reco` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_reco` (`id_user`,`id_produit`),
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`) ON DELETE CASCADE,
  FOREIGN KEY (`id_produit`) REFERENCES `produits`(`id_produit`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Données demo feedbacks
INSERT INTO `feedbacks` (`nom`,`email`,`note`,`type_feedback`,`commentaire`,`statut`) VALUES
('Jean Pierre','jp@gmail.com',5,'Expérience','Excellent service ! Mon HP EliteBook est arrivé rapidement et en parfait état. Je recommande vivement Nexio S.A.','Approuvé'),
('Marie Dupont','md@yahoo.com',5,'Expérience','Super plateforme ! Les prix sont compétitifs et le support via NEX est vraiment utile. Je reviendrai !','Approuvé'),
('Paul Bastien','pb@hotmail.com',4,'Expérience','Bonne expérience globale. La livraison était rapide et les produits de qualité. Très satisfait de mon SSD Kingston.','Approuvé'),
('Carla François','cf@gmail.com',5,'Expérience','Je recommande à 100% ! L\'assistant NEX m\'a aidé à choisir le bon routeur pour mon bureau. Service impeccable.','Approuvé');

SET FOREIGN_KEY_CHECKS = 1;

-- ── PRÉFÉRENCES UTILISATEUR ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `preferences_utilisateur` (
  `id_pref` INT AUTO_INCREMENT PRIMARY KEY,
  `id_user` INT NOT NULL UNIQUE,
  `email_marketing` TINYINT(1) NOT NULL DEFAULT 1,
  `notif_whatsapp` TINYINT(1) NOT NULL DEFAULT 0,
  `notif_app` TINYINT(1) NOT NULL DEFAULT 1,
  `recommandations_nex` TINYINT(1) NOT NULL DEFAULT 1,
  `alerte_prix` TINYINT(1) NOT NULL DEFAULT 1,
  `alerte_stock` TINYINT(1) NOT NULL DEFAULT 1,
  `promo_newsletter` TINYINT(1) NOT NULL DEFAULT 1,
  `campagne_facebook` TINYINT(1) NOT NULL DEFAULT 0,
  `categories_interets` TEXT DEFAULT NULL,
  `budget_min` INT DEFAULT NULL,
  `budget_max` INT DEFAULT NULL,
  `marques_favorites` TEXT DEFAULT NULL,
  `frequence` ENUM('immédiat','quotidien','hebdomadaire') NOT NULL DEFAULT 'quotidien',
  `date_modif` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── CAMPAGNES ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `campagnes` (
  `id_campagne` INT AUTO_INCREMENT PRIMARY KEY,
  `nom` VARCHAR(200) NOT NULL,
  `canal` ENUM('Email','WhatsApp','Facebook','Notification') DEFAULT 'Email',
  `contenu` TEXT,
  `statut` ENUM('Brouillon','En cours','Envoyée','Annulée') NOT NULL DEFAULT 'Brouillon',
  `date_envoi` DATETIME DEFAULT NULL,
  `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- ════════════════════════════════════════════════════════════════
-- MISE À JOUR MAJEURE v2.1 — Marketing IA + Agents + Analyse
-- ════════════════════════════════════════════════════════════════

-- ── CAMPAGNES (remplacement avec nouveaux champs) ─────────────
DROP TABLE IF EXISTS `messages_marketing`;
DROP TABLE IF EXISTS `campagnes`;

CREATE TABLE `campagnes` (
  `id_campagne` INT AUTO_INCREMENT PRIMARY KEY,
  `nom` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `canal` ENUM('Email','WhatsApp','Facebook','Notification','Multi-canal') DEFAULT 'Email',
  `type` ENUM('personnalisée','segment','globale') DEFAULT 'globale',
  `segment` VARCHAR(100) DEFAULT NULL,
  `id_user_cible` INT DEFAULT NULL,
  `titre_ia` VARCHAR(300) DEFAULT NULL,
  `slogan` VARCHAR(300) DEFAULT NULL,
  `contenu` TEXT DEFAULT NULL,
  `contenu_email` TEXT DEFAULT NULL,
  `contenu_whatsapp` TEXT DEFAULT NULL,
  `contenu_facebook` TEXT DEFAULT NULL,
  `appel_action` VARCHAR(200) DEFAULT NULL,
  `statut` ENUM('Brouillon','Planifiée','En cours','Envoyée','Terminée','Annulée') NOT NULL DEFAULT 'Brouillon',
  `date_debut` DATETIME DEFAULT NULL,
  `date_fin` DATETIME DEFAULT NULL,
  `date_envoi` DATETIME DEFAULT NULL,
  `nb_destins` INT DEFAULT 0,
  `nb_envoyes` INT DEFAULT 0,
  `nb_ouverts` INT DEFAULT 0,
  `nb_clics` INT DEFAULT 0,
  `revenus_generes` DECIMAL(12,2) DEFAULT 0,
  `analyse_ia` TEXT DEFAULT NULL,
  `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_user_cible`) REFERENCES `users`(`id_user`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── MESSAGES MARKETING ────────────────────────────────────────
CREATE TABLE `messages_marketing` (
  `id_msg` INT AUTO_INCREMENT PRIMARY KEY,
  `id_campagne` INT NOT NULL,
  `id_user` INT DEFAULT NULL,
  `canal` VARCHAR(50) NOT NULL,
  `contenu` TEXT NOT NULL,
  `statut` ENUM('Envoyé','Ouvert','Cliqué','Échec') DEFAULT 'Envoyé',
  `date_envoi` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_campagne`) REFERENCES `campagnes`(`id_campagne`) ON DELETE CASCADE,
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── PROFILS IA UTILISATEURS ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `profils_ia` (
  `id_profil` INT AUTO_INCREMENT PRIMARY KEY,
  `id_user` INT NOT NULL UNIQUE,
  `centres_interet` TEXT DEFAULT NULL,
  `score_achat` INT DEFAULT 0,
  `probabilite_achat` DECIMAL(5,2) DEFAULT 0,
  `categorie_preferee` VARCHAR(100) DEFAULT NULL,
  `budget_moyen` DECIMAL(12,2) DEFAULT 0,
  `frequence_achat` VARCHAR(50) DEFAULT NULL,
  `segment` VARCHAR(100) DEFAULT NULL,
  `comportement` TEXT DEFAULT NULL,
  `recommandations` TEXT DEFAULT NULL,
  `derniere_analyse` DATETIME DEFAULT NULL,
  `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── PUBLICITÉS PERSONNALISÉES ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `publicites` (
  `id_pub` INT AUTO_INCREMENT PRIMARY KEY,
  `titre` VARCHAR(200) NOT NULL,
  `contenu` TEXT NOT NULL,
  `image_url` VARCHAR(500) DEFAULT NULL,
  `lien` VARCHAR(500) DEFAULT NULL,
  `segment_cible` VARCHAR(100) DEFAULT NULL,
  `categorie_cible` INT DEFAULT NULL,
  `statut` ENUM('Active','Inactive') DEFAULT 'Active',
  `impressions` INT DEFAULT 0,
  `clics` INT DEFAULT 0,
  `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`categorie_cible`) REFERENCES `categories`(`id_categorie`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── LOG ANALYSES IA ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `log_analyses_ia` (
  `id_log` INT AUTO_INCREMENT PRIMARY KEY,
  `agent` VARCHAR(50) NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `statut` ENUM('succès','erreur','en_cours') DEFAULT 'succès',
  `duree_ms` INT DEFAULT 0,
  `tokens_utilises` INT DEFAULT 0,
  `detail` TEXT DEFAULT NULL,
  `date_log` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Données démo campagnes
INSERT INTO `campagnes`(`nom`,`description`,`canal`,`type`,`segment`,`titre_ia`,`slogan`,`contenu`,`appel_action`,`statut`,`date_debut`,`date_fin`,`nb_destins`) VALUES
('Rentrée Gaming 2025','Campagne pour les gamers haïtiens','Email','segment','gamers','🎮 La Rentrée Gaming commence chez Nexio !','Équipez-vous pour dominer','Profitez de nos offres exclusives sur le matériel gaming : GPU ASUS ROG, périphériques Logitech Gaming et bien plus.','Découvrir les offres gaming','Envoyée','2025-08-01','2025-08-31',45),
('Clients Fidèles — Merci !','Récompenses clients fidèles','WhatsApp','segment','fidèles','❤️ Nexio vous remercie de votre fidélité','Toujours là pour vous','Cher client fidèle, en remerciement de votre confiance, profitez de -10% sur votre prochain achat.','Récupérer mon code','Envoyée','2025-09-01','2025-09-30',120),
('Soldes Réseau Entreprises','Matériel réseau B2B','Multi-canal','segment','entreprises','📡 Solutions Réseau Pro à prix réduits','Connectez votre succès','Switches, routeurs WiFi 6, NAS — solutions complètes pour votre infrastructure réseau.','Demander un devis','En cours','2025-10-01','2025-10-31',30);


-- ════════════════════════════════════════════════════════════
-- MISE À JOUR v2.2 — Tables manquantes
-- ════════════════════════════════════════════════════════════

-- ── FOURNISSEURS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `fournisseurs` (
  `id_fournisseur` INT AUTO_INCREMENT PRIMARY KEY,
  `nom` VARCHAR(200) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `telephone` VARCHAR(30) DEFAULT NULL,
  `adresse` VARCHAR(300) DEFAULT NULL,
  `pays` VARCHAR(100) DEFAULT NULL,
  `statut` ENUM('Actif','Inactif') NOT NULL DEFAULT 'Actif',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Ajouter colonne fournisseur aux produits si elle n'existe pas
-- (sera ignoré si la colonne existe déjà)
ALTER TABLE `produits`
  ADD COLUMN IF NOT EXISTS `id_fournisseur` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `cout` DECIMAL(12,2) DEFAULT NULL;

-- ── AVIS (si non créé) ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `avis` (
  `id_avis` INT AUTO_INCREMENT PRIMARY KEY,
  `id_user` INT DEFAULT NULL,
  `id_produit` INT NOT NULL,
  `note` TINYINT NOT NULL DEFAULT 5,
  `commentaire` TEXT DEFAULT NULL,
  `sentiment` VARCHAR(20) DEFAULT NULL,
  `sentiment_score` DECIMAL(4,2) DEFAULT NULL,
  `statut` ENUM('En attente','Approuvé','Rejeté') DEFAULT 'En attente',
  `date_avis` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`) ON DELETE SET NULL,
  FOREIGN KEY (`id_produit`) REFERENCES `produits`(`id_produit`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Données fournisseurs démo
INSERT INTO `fournisseurs` (`nom`,`email`,`telephone`,`pays`) VALUES
('Tech Import Haiti','import@techhaiti.ht','509-3712-5000','Haïti'),
('Global IT Supplies','sales@globalit.com','+1-305-555-0100','USA'),
('Lenovo Latin America','lenovo@lamed.com','+55-11-3054-0000','Brésil'),
('HP Caribbean','hpcarib@hp.com','+1-809-200-1000','République Dominicaine'),
('Seagate Distribution','distrib@seagate.com','+1-408-658-1000','USA');

