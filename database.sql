-- Base de données pour le site Lydia
-- Importez ce fichier via PHPMyAdmin sur localhost:8888

CREATE DATABASE IF NOT EXISTS lydia_cms;
USE lydia_cms;

-- Table pour les paramètres d'administration
CREATE TABLE admin_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table pour les sections du site
CREATE TABLE site_sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section_name VARCHAR(100) UNIQUE NOT NULL,
    section_data JSON,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table pour les sessions administrateur
CREATE TABLE admin_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    admin_id INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT
);

-- Table pour les tentatives de connexion (sécurité anti brute-force)
CREATE TABLE login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success TINYINT(1) DEFAULT 0
);

-- Insertion des données par défaut
INSERT INTO admin_settings (setting_key, setting_value) VALUES 
('admin_password', '$2y$10$3xOAzjE1JTVJYOKhNkWdqOHtOcJdCcBfVqYpZ4rL.ZPqBkxOo.G46'), -- 'admin'
('site_title', 'Votre Nom | Bilan de Compétences & Avenir Professionnel'),
('admin_email', 'admin@example.com'),
('site_url', 'http://localhost:8888/lydia/');

-- Données par défaut pour les sections
INSERT INTO site_sections (section_name, section_data) VALUES 
('hero', JSON_OBJECT(
    'title', 'Révélez votre potentiel.<br>Redessinez votre avenir.',
    'subtitle', 'Accompagnement personnalisé pour trouver le chemin professionnel qui vous ressemble vraiment.',
    'cta_text', 'Prendre un rendez-vous gratuit',
    'background_image', 'https://images.unsplash.com/photo-1543269865-cbf427effbad?q=80&w=2070&auto=format&fit=crop'
)),

('about', JSON_OBJECT(
    'title', 'À propos de moi',
    'subtitle', 'Plus qu\'un métier, une vocation : la vôtre.',
    'description1', 'Après 15 ans dans les ressources humaines, j\'ai choisi de me consacrer à ce qui m\'anime le plus : l\'humain. Ma mission est de vous fournir les outils et la clarté nécessaires pour que vous puissiez, à votre tour, vous épanouir dans une carrière qui a du sens.',
    'description2', 'Mon approche est humaine, bienveillante et structurée. Elle allie les outils concrets du bilan de compétences à une écoute active de vos aspirations profondes.',
    'image', 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=1888&auto=format&fit=crop'
)),

('target_audience', JSON_OBJECT(
    'title', 'Est-ce que cet accompagnement est fait pour vous ?',
    'subtitle', 'Si vous vous reconnaissez dans l\'une de ces situations, alors la réponse est probablement oui.',
    'cards', JSON_ARRAY(
        JSON_OBJECT('icon', 'fa-solid fa-compass', 'title', 'En reconversion', 'description', 'Vous avez une idée mais n\'osez pas sauter le pas, ou au contraire, vous êtes dans le flou total et cherchez une nouvelle voie.'),
        JSON_OBJECT('icon', 'fa-solid fa-arrow-trend-up', 'title', 'En quête d\'évolution', 'description', 'Vous vous sentez à l\'étroit dans votre poste actuel et souhaitez évoluer, mais ne savez pas comment valoriser vos compétences.'),
        JSON_OBJECT('icon', 'fa-solid fa-seedling', 'title', 'En manque de sens', 'description', 'Votre travail ne vous passionne plus. Vous cherchez à aligner votre vie professionnelle avec vos valeurs personnelles.')
    )
)),

('process', JSON_OBJECT(
    'title', 'Un cheminement en 3 phases clés',
    'subtitle', 'Le bilan est un voyage structuré que nous faisons ensemble. Loin d\'être un simple test, c\'est un dialogue constructif pour co-créer votre avenir.',
    'phases', JSON_ARRAY(
        JSON_OBJECT('title', 'Phase 1 : L\'Investigation', 'description', 'Nous analysons votre parcours, vos expériences, mais surtout vos envies profondes pour comprendre qui vous êtes et ce qui vous motive.'),
        JSON_OBJECT('title', 'Phase 2 : L\'Exploration', 'description', 'Nous explorons les pistes professionnelles possibles, nous enquêtons sur les métiers et les formations pour construire un projet réaliste.'),
        JSON_OBJECT('title', 'Phase 3 : La Construction', 'description', 'Vous repartez avec un plan d\'action clair, des étapes définies et une synthèse écrite pour mettre en œuvre votre projet en toute confiance.')
    )
)),

('benefits', JSON_OBJECT(
    'title', 'Les bénéfices concrets de notre collaboration',
    'cards', JSON_ARRAY(
        JSON_OBJECT('icon', 'fa-solid fa-lightbulb', 'title', 'Clarté & Vision', 'description', 'Repartez avec un projet professionnel clair, défini et qui vous ressemble.'),
        JSON_OBJECT('icon', 'fa-solid fa-mountain-sun', 'title', 'Confiance Retrouvée', 'description', 'Prenez conscience de vos forces, de vos talents et de votre valeur unique.'),
        JSON_OBJECT('icon', 'fa-solid fa-map-signs', 'title', 'Plan d\'Action', 'description', 'Obtenez une feuille de route précise et des étapes concrètes pour vos objectifs.'),
        JSON_OBJECT('icon', 'fa-solid fa-heart', 'title', 'Sens & Épanouissement', 'description', 'Alignez enfin votre carrière avec ce qui compte vraiment pour vous.'),
        JSON_OBJECT('icon', 'fa-solid fa-toolbox', 'title', 'Outils Personnalisés', 'description', 'Disposez d\'outils sur-mesure pour continuer à piloter votre carrière en autonomie.'),
        JSON_OBJECT('icon', 'fa-solid fa-network-wired', 'title', 'Réseau & Opportunités', 'description', 'Apprenez à développer votre réseau et à identifier les bonnes opportunités.')
    )
)),

('faq', JSON_OBJECT(
    'title', 'Vos questions, nos réponses',
    'subtitle', 'Voici les réponses aux questions les plus fréquentes pour vous aider à y voir plus clair.',
    'items', JSON_ARRAY(
        JSON_OBJECT('question', 'Combien de temps dure un bilan de compétences ?', 'answer', 'Un bilan de compétences dure généralement jusqu\'à 24 heures, réparties sur plusieurs semaines. Cela nous laisse le temps d\'approfondir chaque étape sans se presser, avec des séances de 2 à 3 heures.'),
        JSON_OBJECT('question', 'Mon bilan est-il finançable par le CPF ?', 'answer', 'Oui, absolument. Le bilan de compétences est une formation éligible au Compte Personnel de Formation (CPF). Je vous accompagnerai dans les démarches pour utiliser vos droits et financer votre accompagnement.'),
        JSON_OBJECT('question', 'Les séances peuvent-elles se faire à distance ?', 'answer', 'Oui, je propose des accompagnements en présentiel dans mon cabinet à Paris, mais également 100% à distance par visioconférence. Nous choisissons ensemble la formule qui vous convient le mieux.')
    )
)),

('contact', JSON_OBJECT(
    'title', 'Faisons le premier pas ensemble',
    'subtitle', 'Prêt(e) à discuter de votre avenir ? Utilisez le formulaire ci-dessous pour toute question ou pour planifier notre premier appel découverte, gratuit et sans engagement.',
    'email', 'contact@votrenom.fr',
    'phone', '01 23 45 67 89',
    'address', '123 Rue de l\'Avenir<br>75000 Paris, France'
)),

('footer', JSON_OBJECT(
    'logo', 'Votre Nom',
    'address', '123 Rue de l\'Avenir<br>75000 Paris, France',
    'email', 'contact@votrenom.fr',
    'phone', '01 23 45 67 89',
    'social_linkedin', '#',
    'social_instagram', '#',
    'copyright', '2025 Votre Nom - Tous droits réservés.'
)),

('design', JSON_OBJECT(
    'vert_sauge', '#A3B18A',
    'beige_rose', '#F2E8DF',
    'creme', '#FEFBF6',
    'gris_anthracite', '#343A40',
    'dore', '#B99470',
    'dore_clair', '#d1b59a'
));

-- Table pour les messages de contact
CREATE TABLE contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL
);
