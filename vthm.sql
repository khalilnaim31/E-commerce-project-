CREATE DATABASE IF NOT EXISTS vthm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vthm;

DROP TABLE IF EXISTS utilisateurs;
CREATE TABLE utilisateurs (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom        VARCHAR(120)  NOT NULL,
  email      VARCHAR(180)  NOT NULL UNIQUE,
  password   VARCHAR(255)  NOT NULL,          
  role       ENUM('client','admin') NOT NULL DEFAULT 'client',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP TABLE IF EXISTS produits;
CREATE TABLE produits (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  genre       ENUM('homme','femme') NOT NULL,
  categorie   VARCHAR(60)  NOT NULL,
  nom         VARCHAR(200) NOT NULL,
  prix        DECIMAL(8,2) NOT NULL,
  tag         VARCHAR(60)  DEFAULT '',
  img         TEXT         NOT NULL,
  description TEXT         DEFAULT '',
  actif       TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO produits (genre, categorie, nom, prix, tag, img, description) VALUES

('homme','vestes',    'Veste Structurée Gris Charbon', 289.00, 'Nouveau',
 'https://images.unsplash.com/photo-1617137968427-85924c800a22?w=600&q=80',
 'Veste en laine structurée, coupe ajustée, doublure satinée.'),

('homme','vestes',    'Manteau Long Camel',            495.00, '',
 'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?w=600&q=80',
 'Manteau long en laine mélangée, coupe droite, boutons dorés.'),

('homme','chemises',  'Chemise Oxford Blanc Cassé',    125.00, 'Bestseller',
 'https://images.unsplash.com/photo-1609388978000-27b0c1fe42c5?w=600&q=80',
 'Chemise en coton Oxford, col classique, finitions soignées.'),

('homme','pantalons', 'Jean Slim Indigo Brut',         185.00, '',
 'https://images.unsplash.com/photo-1541099649105-f69ad21f3246?w=600&q=80',
 'Jean slim en denim brut japonais, taille mi-haute.'),

('homme','pulls',     'Pull Col Roulé Mérino Noir',    210.00, 'Nouveau',
 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=600&q=80',
 'Pull col roulé en laine mérino extra-fine, doux et chaud.'),

('homme','vestes',    'Blazer Croisé Marine',          345.00, '',
 'https://images.unsplash.com/photo-1593030761757-71fae45fa0e7?w=600&q=80',
 'Blazer croisé en tweed marine, épaulettes renforcées.'),

('homme','pantalons', 'Pantalon Chino Sable',          155.00, '',
 'https://images.unsplash.com/photo-1473966968600-fa801b869a1a?w=600&q=80',
 'Chino en coton stretch, coupe slim, teinture naturelle.'),

('homme','tops',      'T-Shirt Coton Pima Blanc',       72.00, '-20%',
 'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?w=600&q=80',
 'T-shirt en coton Pima péruvien, coupe légèrement oversize.'),

('femme','robes',     'Robe Midi Soie Ivoire',         320.00, 'Nouveau',
 'https://images.unsplash.com/photo-1581044777550-4cfa60707c03?w=600&q=80',
 'Robe midi en soie ivoire, décolleté V, ceinture fine incluse.'),

('femme','vestes',    'Blazer Crème Oversize',         285.00, '',
 'https://images.unsplash.com/photo-1539109136881-3be0616acf4b?w=600&q=80',
 'Blazer oversize en crêpe crème, poches plaquées, manches 3/4.'),

('femme','pantalons', 'Pantalon Tailleur Noir',        195.00, 'Bestseller',
 'https://images.unsplash.com/photo-1469334031218-e382a71b716b?w=600&q=80',
 'Pantalon tailleur droit en laine fine, pli marqué, taille haute.'),

('femme','tops',      'Top Soie Blush',                145.00, '',
 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=600&q=80',
 'Top en soie blush, bretelles fines, tombé élégant.'),

('femme','vestes',    'Manteau Laine Caramel',         520.00, 'Nouveau',
 'https://images.unsplash.com/photo-1594938298603-c8148c4b4545?w=600&q=80',
 'Manteau en laine bouillie caramel, col châle, boutons corne.'),

('femme','tops',      'Chemisier Fluide Écru',         135.00, '',
 'https://images.unsplash.com/photo-1618354691438-25bc04584c23?w=600&q=80',
 'Chemisier en viscose fluide écru, manches longues, col bohème.'),

('femme','robes',     'Robe Longue Velours Nuit',      370.00, '-15%',
 'https://images.unsplash.com/photo-1550614000-4895a10e1bfd?w=600&q=80',
 'Robe longue en velours bleu nuit, dos nu, fente sur le côté.'),

('femme','pantalons', 'Jupe Midi Plissée Taupe',       175.00, '',
 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=600&q=80',
 'Jupe midi plissée en satin taupe, taille élastiquée.');

DROP TABLE IF EXISTS panier_items;
CREATE TABLE panier_items (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_token  VARCHAR(64)  NOT NULL,        
  user_id        INT UNSIGNED DEFAULT NULL,      
  produit_id     INT UNSIGNED NOT NULL,
  taille         VARCHAR(10)  NOT NULL DEFAULT 'M',
  quantite       SMALLINT     NOT NULL DEFAULT 1,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS commandes;
CREATE TABLE commandes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED DEFAULT NULL,
  session_token VARCHAR(64)  NOT NULL,
  total         DECIMAL(10,2) NOT NULL,
  livraison     DECIMAL(6,2)  NOT NULL DEFAULT 0,
  statut        ENUM('en_attente','confirmee','expediee','livree','annulee') DEFAULT 'en_attente',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS commande_lignes;
CREATE TABLE commande_lignes (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  commande_id  INT UNSIGNED NOT NULL,
  produit_id   INT UNSIGNED DEFAULT NULL,
  nom_produit  VARCHAR(200) NOT NULL,
  prix_unitaire DECIMAL(8,2) NOT NULL,
  taille       VARCHAR(10)  NOT NULL,
  quantite     SMALLINT     NOT NULL,
  FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
  FOREIGN KEY (produit_id)  REFERENCES produits(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_produits_genre     ON produits(genre);
CREATE INDEX idx_produits_categorie ON produits(categorie);
CREATE INDEX idx_panier_session     ON panier_items(session_token);
CREATE INDEX idx_commandes_user     ON commandes(user_id);
