-- ============================================================================
-- GVP - Gestion des Visites Préventives
-- Script SQL complet : structure + relations (FK) + données de test (seed)
-- Compatible XAMPP / MySQL 8+ / MariaDB 10.4+
-- Encodage : utf8mb4
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Création de la base de données
-- ----------------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `gvp_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `gvp_db`;

-- ----------------------------------------------------------------------------
-- 2. Table : utilisateurs
--    (remplace l'ancienne table "users" du prototype initial)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `journal_activite`;
DROP TABLE IF EXISTS `analytics_kpi`;
DROP TABLE IF EXISTS `rapports`;
DROP TABLE IF EXISTS `visites`;
DROP TABLE IF EXISTS `sites`;
DROP TABLE IF EXISTS `clients`;
DROP TABLE IF EXISTS `utilisateurs`;

CREATE TABLE `utilisateurs` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nom`           VARCHAR(100)  NOT NULL,
  `prenom`        VARCHAR(100)  NOT NULL,
  `email`         VARCHAR(190)  NOT NULL,
  `mot_de_passe`  VARCHAR(255)  NOT NULL,
  `telephone`     VARCHAR(30)   DEFAULT NULL,
  `ville`         VARCHAR(100)  DEFAULT NULL,
  `role`          ENUM('administrateur','superviseur','technicien') NOT NULL DEFAULT 'technicien',
  `actif`         TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_utilisateurs_email` (`email`),
  KEY `idx_utilisateurs_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Table : clients
-- ----------------------------------------------------------------------------
CREATE TABLE `clients` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nom_entreprise` VARCHAR(150) NOT NULL,
  `contact_nom`    VARCHAR(150) DEFAULT NULL,
  `email`          VARCHAR(190) DEFAULT NULL,
  `telephone`      VARCHAR(30)  DEFAULT NULL,
  `adresse`        VARCHAR(255) DEFAULT NULL,
  `ville`          VARCHAR(100) DEFAULT NULL,
  `actif`          TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_clients_ville` (`ville`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Table : sites (sites appartenant à un client)
-- ----------------------------------------------------------------------------
CREATE TABLE `sites` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `client_id`   INT UNSIGNED NOT NULL,
  `nom_site`    VARCHAR(150) NOT NULL,
  `adresse`     VARCHAR(255) DEFAULT NULL,
  `ville`       VARCHAR(100) DEFAULT NULL,
  `latitude`    DECIMAL(10,6) DEFAULT NULL,
  `longitude`   DECIMAL(10,6) DEFAULT NULL,
  `actif`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_sites_client` (`client_id`),
  CONSTRAINT `fk_sites_client`
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. Table : visites (visites préventives planifiées sur un site)
-- ----------------------------------------------------------------------------
CREATE TABLE `visites` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `site_id`        INT UNSIGNED NOT NULL,
  `technicien_id`  INT UNSIGNED DEFAULT NULL,
  `type_visite`    VARCHAR(100) NOT NULL DEFAULT 'Visite préventive',
  `date_prevue`    DATE NOT NULL,
  `date_realisee`  DATE DEFAULT NULL,
  `statut`         ENUM('planifiee','en_cours','realisee','annulee') NOT NULL DEFAULT 'planifiee',
  `notes`          TEXT DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_visites_site` (`site_id`),
  KEY `idx_visites_technicien` (`technicien_id`),
  KEY `idx_visites_statut` (`statut`),
  KEY `idx_visites_date_prevue` (`date_prevue`),
  CONSTRAINT `fk_visites_site`
    FOREIGN KEY (`site_id`) REFERENCES `sites`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_visites_technicien`
    FOREIGN KEY (`technicien_id`) REFERENCES `utilisateurs`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. Table : rapports (rapport rédigé suite à une visite)
-- ----------------------------------------------------------------------------
CREATE TABLE `rapports` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `visite_id`        INT UNSIGNED NOT NULL,
  `redige_par`       INT UNSIGNED DEFAULT NULL,
  `valide_par`       INT UNSIGNED DEFAULT NULL,
  `titre`            VARCHAR(200) NOT NULL DEFAULT 'Rapport de visite',
  `contenu`          TEXT DEFAULT NULL,
  `statut`           ENUM('brouillon','soumis','valide','rejete') NOT NULL DEFAULT 'brouillon',
  `date_soumission`  DATETIME DEFAULT NULL,
  `date_validation`  DATETIME DEFAULT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_rapports_visite` (`visite_id`),
  KEY `idx_rapports_redige_par` (`redige_par`),
  KEY `idx_rapports_valide_par` (`valide_par`),
  KEY `idx_rapports_statut` (`statut`),
  CONSTRAINT `fk_rapports_visite`
    FOREIGN KEY (`visite_id`) REFERENCES `visites`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rapports_redige_par`
    FOREIGN KEY (`redige_par`) REFERENCES `utilisateurs`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_rapports_valide_par`
    FOREIGN KEY (`valide_par`) REFERENCES `utilisateurs`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. Table : journal_activite (traçabilité des actions utilisateurs)
-- ----------------------------------------------------------------------------
CREATE TABLE `journal_activite` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `utilisateur_id` INT UNSIGNED DEFAULT NULL,
  `action`         VARCHAR(100) NOT NULL,
  `description`    VARCHAR(255) DEFAULT NULL,
  `ip_address`     VARCHAR(45)  DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_journal_utilisateur` (`utilisateur_id`),
  KEY `idx_journal_action` (`action`),
  CONSTRAINT `fk_journal_utilisateur`
    FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. Table : analytics_kpi (résultats calculés par le module Python)
-- ----------------------------------------------------------------------------
CREATE TABLE `analytics_kpi` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nom_kpi`     VARCHAR(100) NOT NULL,
  `valeur`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `unite`       VARCHAR(20)  DEFAULT NULL,
  `periode`     VARCHAR(50)  DEFAULT NULL,
  `metadata`    JSON         DEFAULT NULL,
  `date_calcul` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_kpi_nom` (`nom_kpi`),
  KEY `idx_kpi_date` (`date_calcul`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

