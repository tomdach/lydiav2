# 🌟 LydiaV2 - CMS de Site Web Professionnel

Un système de gestion de contenu moderne et intuitif pour créer et gérer facilement un site web professionnel.

## ✨ Fonctionnalités

- 🎨 **Interface d'administration moderne** - Dashboard intuitif avec prévisualisation en temps réel
- 📝 **Éditeur de contenu visuel** - Modifiez toutes les sections de votre site facilement
- � **Gestion des messages** - Système complet de réception et réponse aux messages de contact
- 🎯 **Sections personnalisables** :
  - Page d'accueil (Hero)
  - À propos
  - Public cible
  - Processus/Bilan
  - Bénéfices
  - FAQ
  - Contact
  - Footer
- 🎨 **Personnalisation des couleurs** - Palette de couleurs personnalisable
- 📱 **Design responsive** - Optimisé pour tous les appareils
- 🔒 **Sécurisé** - Protection CSRF, sanitisation des données
- 📧 **Système de contact** - Formulaire avec notification en temps réel

## 📋 Prérequis

- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Serveur web (Apache/Nginx) ou MAMP/WAMP/XAMPP pour le développement local
- Navigateur web moderne

## � Installation

### Étape 1 : Clonage du projet
```bash
git clone https://github.com/[votre-username]/lydiav2.git
cd lydiav2
```

### Étape 2 : Configuration de la base de données

### Étape 2 : Configuration de la base de données
1. Créez une base de données MySQL nommée `lydia_cms`
2. Importez le fichier `database.sql` :
   ```bash
   mysql -u root -p lydia_cms < database.sql
   ```
   Ou via PHPMyAdmin : `http://localhost:8888/phpMyAdmin/`

### Étape 3 : Configuration PHP
1. Copiez `admin/config.php.example` vers `admin/config.php`
2. Modifiez les paramètres de connexion :
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'lydia_cms');
   define('DB_USER', 'votre_utilisateur');
   define('DB_PASS', 'votre_mot_de_passe');
   define('DB_PORT', '3306');
   ```

### Étape 4 : Permissions
```bash
chmod 755 uploads/
chmod 644 admin/config.php
```

## 🎯 Accès à l'administration

### Connexion
- URL : `http://localhost/lydiav2/admin/`
- Mot de passe par défaut : `admin`

⚠️ **Important** : Changez le mot de passe par défaut après la première connexion !

## 📚 Guide d'utilisation

### Dashboard
Le tableau de bord vous donne un aperçu général :
- Statistiques des messages non lus
- Actions rapides vers les sections principales
- Messages récents
- Guide d'utilisation

### Édition des sections
1. Sélectionnez une section dans le menu de gauche
2. Modifiez le contenu dans les formulaires
3. Visualisez les changements en temps réel dans la prévisualisation
4. Cliquez sur "Sauvegarder" pour publier

### Gestion des messages
- Réception automatique des messages du formulaire de contact
- Système de notification en temps réel
- Réponses directes depuis l'interface
- Marquage des messages comme lus/non lus
- Historique des réponses

## 🔧 Configuration avancée

### Personnalisation des couleurs
Accédez à la section "Couleurs" pour personnaliser :
- Couleur primaire
- Couleur secondaire
- Couleurs d'accentuation
- Prévisualisation en temps réel

### Sécurité
- Protection CSRF activée
- Sanitisation automatique des données
- Sessions sécurisées
- Mot de passe hashé

## � Structure du projet

```
lydiav2/
├── admin/                  # Interface d'administration
│   ├── config.php         # Configuration de la base de données
│   ├── index.php          # Dashboard principal
│   ├── login.php          # Page de connexion
│   ├── admin.js           # Scripts JavaScript
│   └── ...
├── uploads/               # Dossier pour les fichiers uploadés
├── database.sql          # Structure de la base de données
├── index.php            # Page principale du site
├── index_dynamic.php    # Version dynamique (à renommer)
└── README.md           # Ce fichier
```

## 🐛 Dépannage

### Erreur de connexion à la base de données
Vérifiez les paramètres dans `admin/config.php`

### Page blanche
Activez l'affichage des erreurs PHP :
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

### Problèmes de permissions
```bash
chmod -R 755 .
chmod 644 admin/config.php
```

## 🤝 Contribution

1. Fork le projet
2. Créez une branche pour votre fonctionnalité (`git checkout -b feature/nouvelle-fonctionnalite`)
3. Committez vos changements (`git commit -am 'Ajout d'une nouvelle fonctionnalité'`)
4. Push vers la branche (`git push origin feature/nouvelle-fonctionnalite`)
5. Créez une Pull Request

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 👨‍💻 Auteur

Développé avec ❤️ pour faciliter la création de sites web professionnels.

## 🆘 Support

Pour toute question ou problème :
- Ouvrez une issue sur GitHub
- Consultez la documentation
- Vérifiez les logs d'erreur PHP

---

*Dernière mise à jour : Juillet 2025*

### Modification des sections
1. **Section Accueil** : Titre principal, sous-titre, bouton d'action, image de fond
2. **Section À propos** : Titre, sous-titre, descriptions, image de profil
3. **Section Pour qui** : Titre, sous-titre, cartes avec icônes
4. **Section Le Bilan** : Titre, sous-titre, phases du processus
5. **Section Bénéfices** : Titre, cartes de bénéfices avec icônes
6. **Section FAQ** : Questions-réponses dynamiques
7. **Section Contact** : Formulaire de contact personnalisable
8. **Footer** : Informations de contact, réseaux sociaux
9. **Couleurs** : Palette de couleurs du site

### Fonctionnalités clés
- ✅ **Prévisualisation en temps réel** : Voyez vos modifications instantanément
- ✅ **Sauvegarde en base** : Toutes les données sont stockées en MySQL
- ✅ **Interface responsive** : Fonctionne sur mobile et desktop
- ✅ **Sécurité intégrée** : Protection CSRF, anti brute-force
- ✅ **Design moderne** : Interface épurée avec Tailwind CSS

## 🔒 Sécurité

### Fonctionnalités de sécurité incluses
- **Protection anti brute-force** : 5 tentatives maximum, blocage 15 minutes
- **Tokens CSRF** : Protection contre les attaques cross-site
- **Sessions sécurisées** : Timeout automatique, cookies sécurisés
- **Validation des données** : Nettoyage et échappement des entrées utilisateur

### Recommandations
1. Changez le mot de passe par défaut dès la première connexion
2. Modifiez la clé secrète `CSRF_SECRET` dans `config.php`
3. En production, activez HTTPS et modifiez `cookie_secure` à `true`

## 🎨 Personnalisation avancée

### Ajout de nouvelles sections
Pour ajouter une nouvelle section :
1. Ajoutez l'entrée dans la base de données (table `site_sections`)
2. Créez le générateur de champs dans `admin/index.php`
3. Ajoutez la prévisualisation correspondante
4. Intégrez la section dans `index.php`

### Modification des couleurs
Utilisez la section "Couleurs" du dashboard pour personnaliser :
- Vert Sauge : Couleur principale
- Beige Rosé : Couleur d'accentuation
- Crème : Arrière-plan
- Gris Anthracite : Texte principal
- Doré : Boutons et éléments interactifs
- Doré Clair : Dégradés

## 🔧 Dépannage

### Problèmes courants

**Erreur de connexion à la base de données**
- Vérifiez que MAMP est démarré
- Contrôlez les paramètres dans `config.php`
- Assurez-vous que la base de données `lydia_cms` existe

**Page blanche sur l'administration**
- Activez l'affichage des erreurs PHP
- Vérifiez les logs d'erreur
- Contrôlez les permissions des fichiers

**Modifications non sauvegardées**
- Vérifiez la console du navigateur pour les erreurs JavaScript
- Contrôlez les tokens CSRF
- Vérifiez la configuration de la base de données

### Support
Pour toute question ou problème :
1. Vérifiez ce guide d'installation
2. Consultez les logs d'erreur PHP
3. Vérifiez la console du navigateur

## 📁 Structure des fichiers

```
lydia/
├── index.php (dynamique, utilise la BDD)
├── index_static.php (sauvegarde de l'original)
├── database.sql (structure et données)
├── README.md (ce fichier)
└── admin/
    ├── config.php (configuration)
    ├── login.php (page de connexion)
    ├── index.php (dashboard principal)
    └── logout.php (déconnexion)
```

## 🎉 Félicitations !

Votre CMS est maintenant prêt à l'emploi. Vous pouvez modifier tous les contenus de votre site sans toucher au code, directement depuis l'interface d'administration.

Bon développement ! 🚀
