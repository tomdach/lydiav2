<?php
// Inclusion de la configuration
require_once 'admin/config.php';

// Récupération des données de toutes les sections
$heroData = getSectionData('hero');
$aboutData = getSectionData('about');
$targetAudienceData = getSectionData('target_audience');
$processData = getSectionData('process');
$benefitsData = getSectionData('benefits');
$faqData = getSectionData('faq');
$contactData = getSectionData('contact');
$footerData = getSectionData('footer');
$designData = getSectionData('design');

// Valeurs par défaut si les données ne sont pas encore configurées
$heroData = $heroData ?: [
    'title' => 'Révélez votre potentiel.<br>Redessinez votre avenir.',
    'subtitle' => 'Accompagnement personnalisé pour trouver le chemin professionnel qui vous ressemble vraiment.',
    'cta_text' => 'Prendre un rendez-vous gratuit',
    'background_image' => 'https://images.unsplash.com/photo-1543269865-cbf427effbad?q=80&w=2070&auto=format&fit=crop'
];

$aboutData = $aboutData ?: [
    'title' => 'À propos de moi',
    'subtitle' => 'Plus qu\'un métier, une vocation : la vôtre.',
    'description1' => 'Après 15 ans dans les ressources humaines, j\'ai choisi de me consacrer à ce qui m\'anime le plus : l\'humain.',
    'description2' => 'Mon approche est humaine, bienveillante et structurée.',
    'image' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=1888&auto=format&fit=crop'
];

$designData = $designData ?: [
    'vert_sauge' => '#A3B18A',
    'beige_rose' => '#F2E8DF',
    'creme' => '#FEFBF6',
    'gris_anthracite' => '#343A40',
    'dore' => '#B99470',
    'dore_clair' => '#d1b59a'
];

$footerData = $footerData ?: [
    'logo' => 'Votre Nom',
    'address' => '123 Rue de l\'Avenir<br>75000 Paris, France',
    'email' => 'contact@votrenom.fr',
    'phone' => '01 23 45 67 89',
    'social_linkedin' => '#',
    'social_instagram' => '#',
    'copyright' => '2025 Votre Nom - Tous droits réservés.'
];

$contactData = $contactData ?: [
    'title' => 'Faisons le premier pas ensemble',
    'subtitle' => 'Prêt(e) à discuter de votre avenir ?',
    'email' => 'contact@votrenom.fr',
    'phone' => '01 23 45 67 89',
    'address' => '123 Rue de l\'Avenir<br>75000 Paris, France'
];

// Traitement du formulaire de contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_form'])) {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    
    // Ici vous pouvez ajouter l'envoi d'email ou la sauvegarde en base
    // Pour l'instant, on simule un succès
    $contactSuccess = true;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= getSetting('site_title', 'Votre Nom | Bilan de Compétences & Avenir Professionnel') ?></title>
    
    <!-- Importation des polices depuis Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <!-- Icônes pour les bénéfices et le footer -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

    <style>
        /* --------------------------------- */
        /* --- DÉFINITIONS GLOBALES (DESIGN FINAL & OPTIMISÉ) --- */
        /* --------------------------------- */
        :root {
            --vert-sauge: <?= $designData['vert_sauge'] ?>;
            --beige-rose: <?= $designData['beige_rose'] ?>;
            --creme: <?= $designData['creme'] ?>;
            --gris-anthracite: <?= $designData['gris_anthracite'] ?>;
            --dore: <?= $designData['dore'] ?>;
            --dore-clair: <?= $designData['dore_clair'] ?>;
            --font-titre: 'Playfair Display', serif;
            --font-texte: 'Lato', sans-serif;
            --ease-out-cubic: cubic-bezier(0.215, 0.610, 0.355, 1);
            --shadow-soft: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
            --shadow-medium: 0 10px 15px -3px rgb(0 0 0 / 0.07), 0 4px 6px -4px rgb(0 0 0 / 0.07);
            --shadow-large: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        
        body { 
            font-family: var(--font-texte); 
            background-color: var(--creme); 
            color: var(--gris-anthracite); 
            line-height: 1.7; 
            font-size: 16px; 
            cursor: default; 
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* --------------------------------- */
        /* --- ÉLÉMENTS COMMUNS --- */
        /* --------------------------------- */
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        section { padding: 120px 0; overflow: hidden; position: relative; }
        
        h2 { 
            font-family: var(--font-titre);
            font-weight: 700;
            font-size: clamp(2.5rem, 5vw, 3.5rem); /* Typographie Fluide */
            text-align: center; 
            margin-bottom: 20px; 
            line-height: 1.2; 
        }
        h3 { 
            font-family: var(--font-titre);
            font-weight: 700;
            font-size: clamp(1.5rem, 3vw, 1.8rem); /* Typographie Fluide */
            margin-bottom: 20px; 
        }
        
        .section-subtitle { text-align: center; max-width: 700px; margin: 0 auto 80px auto; font-size: 1.1rem; font-weight: 300; }
        .cta-button { display: inline-block; background-image: linear-gradient(45deg, var(--dore), var(--dore-clair)); color: white; padding: 18px 40px; border-radius: 50px; text-decoration: none; font-weight: bold; transition: transform 0.4s var(--ease-out-cubic), box-shadow 0.4s var(--ease-out-cubic); border: none; cursor: pointer; box-shadow: var(--shadow-medium); }
        .cta-button:hover { transform: translateY(-8px); box-shadow: var(--shadow-large); }
        .fade-in { opacity: 0; transform: translateY(40px); transition: opacity 1s var(--ease-out-cubic), transform 1s var(--ease-out-cubic); }
        .fade-in.visible { opacity: 1; transform: translateY(0); }

        /* --------------------------------- */
        /* --- HEADER & NAVIGATION --- */
        /* --------------------------------- */
        .main-header { position: fixed; top: 0; left: 0; width: 100%; padding: 20px 0; z-index: 1000; transition: background-color 0.4s ease, box-shadow 0.4s ease, transform 0.3s ease; }
        .main-header.scrolled { background-color: rgba(254, 251, 246, 0.85); backdrop-filter: blur(10px); box-shadow: var(--shadow-soft); }
        .main-header.hidden { transform: translateY(-100%); }
        .main-header .container { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-family: var(--font-titre); font-size: 1.5rem; font-weight: bold; text-decoration: none; color: var(--gris-anthracite); }
        .main-nav a { margin-left: 25px; text-decoration: none; color: var(--gris-anthracite); font-weight: bold; position: relative; padding-bottom: 8px; transition: color 0.3s ease; font-size: 0.9rem; }
        .main-nav a::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 2px; background-color: var(--dore); transform: scaleX(0); transform-origin: right; transition: transform 0.4s var(--ease-out-cubic); }
        .main-nav a:hover::after, .main-nav a.active::after { transform: scaleX(1); transform-origin: left; }
        .main-nav a.active { color: var(--dore); }

        /* --------------------------------- */
        /* --- SECTION HÉRO (ACCUEIL) --- */
        /* --------------------------------- */
        #accueil { min-height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; position: relative; background-image: url('<?= $heroData['background_image'] ?>'); background-attachment: fixed; background-position: center; background-repeat: no-repeat; background-size: cover; }
        #accueil::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(254, 251, 246, 0.7); }
        .hero-content { position: relative; max-width: 800px; }
        .hero-content h1 { font-size: clamp(3rem, 8vw, 4.5rem); line-height: 1.1; margin-bottom: 20px; }
        .hero-content h1 .char { display: inline-block; opacity: 0; transform: translateY(50px) rotate(10deg); transition: opacity 0.6s var(--ease-out-cubic), transform 0.6s var(--ease-out-cubic); }
        .hero-content p { font-size: 1.2rem; max-width: 600px; margin: 0 auto 40px auto; font-weight: 300; }

        /* --------------------------------- */
        /* --- SECTION "À PROPOS" --- */
        /* --------------------------------- */
        #a-propos .container { display: flex; align-items: center; gap: 80px; }
        #a-propos .about-image { flex: 1; max-width: 400px; }
        #a-propos img { width: 100%; border-radius: 10px; box-shadow: -15px 15px 0 var(--beige-rose); border: 10px solid white; transition: transform 0.4s var(--ease-out-cubic); }
        #a-propos img:hover { transform: scale(1.05) rotate(-2deg); }
        #a-propos .about-text { flex: 1.5; }
        #a-propos::before { content: ''; position: absolute; width: 400px; height: 400px; background-color: var(--beige-rose); border-radius: 45% 55% 70% 30% / 30% 50% 50% 70%; opacity: 0.5; left: -100px; top: 50%; transform: translateY(-50%) rotate(20deg); animation: morph 15s ease-in-out infinite; }
        @keyframes morph { 0% { border-radius: 45% 55% 70% 30% / 30% 50% 50% 70%; } 50% { border-radius: 30% 70% 40% 60% / 60% 30% 70% 40%; } 100% { border-radius: 45% 55% 70% 30% / 30% 50% 50% 70%; } }

        /* --------------------------------- */
        /* --- SECTION "POUR QUI ?" & "BÉNÉFICES" --- */
        /* --------------------------------- */
        .grid-layout { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; }
        .card { background: white; padding: 30px; border-radius: 15px; box-shadow: var(--shadow-medium); transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .card:hover { transform: translateY(-10px); box-shadow: var(--shadow-large); }
        .card h3 { font-size: 1.3rem; display: flex; align-items: center; gap: 10px; }
        .card .icon { color: var(--dore); font-size: 1.5rem; }
        .benefit-card .icon { font-size: 2.5rem; margin-bottom: 20px; display: inline-block; transition: transform 0.3s ease; }
        .benefit-card:hover .icon { transform: scale(1.2) rotate(-10deg); }
        .benefit-card { text-align: center; }

        /* --------------------------------- */
        /* --- SECTION "LE BILAN" --- */
        /* --------------------------------- */
        #le-bilan { background-color: var(--beige-rose); }
        .timeline { position: relative; max-width: 800px; margin: 0 auto; }
        .timeline::before { content: ''; position: absolute; width: 4px; background-color: var(--vert-sauge); opacity: 0.3; top: 0; bottom: 0; left: 50%; margin-left: -2px; }
        .timeline::after { content: ''; position: absolute; width: 4px; background-color: var(--vert-sauge); top: 0; left: 50%; margin-left: -2px; transform: scaleY(0); transform-origin: top; transition: transform 1s ease-in-out; }
        .timeline.visible::after { transform: scaleY(1); }
        .timeline-item { padding: 10px 40px; position: relative; width: 50%; }
        .timeline-item:nth-child(odd) { left: 0; text-align: right; }
        .timeline-item:nth-child(even) { left: 50%; }
        .timeline-item::after { content: ''; position: absolute; width: 20px; height: 20px; right: -10px; background-color: white; border: 4px solid var(--vert-sauge); top: 25px; border-radius: 50%; z-index: 1; transition: transform 0.5s ease; }
        .timeline-item:nth-child(even)::after { left: -10px; }
        .timeline-item:hover::after { transform: scale(1.3); }
        .timeline-content { padding: 20px 30px; background-color: var(--creme); border-radius: 8px; box-shadow: var(--shadow-medium); }

        /* --------------------------------- */
        /* --- SECTION "FAQ" --- */
        /* --------------------------------- */
        #faq { background-color: var(--beige-rose); }
        .faq-accordion { max-width: 800px; margin: 0 auto; }
        .faq-item { background: white; margin-bottom: 10px; border-radius: 8px; box-shadow: var(--shadow-soft); overflow: hidden; }
        .faq-question { padding: 20px; font-weight: bold; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .faq-question::after { content: '\f078'; font-family: 'Font Awesome 6 Free'; font-weight: 900; color: var(--dore); transition: transform 0.3s ease; }
        .faq-item.active .faq-question::after { transform: rotate(180deg); }
        .faq-answer { max-height: 0; overflow: hidden; transition: max-height 0.5s var(--ease-out-cubic), padding 0.5s ease; }
        .faq-answer p { padding: 0 20px 20px 20px; }
        .faq-item.active .faq-answer { max-height: 200px; }
        
        /* --------------------------------- */
        /* --- SECTION "CONTACT" --- */
        /* --------------------------------- */
        .contact-form { max-width: 700px; margin: 0 auto; background: white; padding: 50px; border-radius: 10px; box-shadow: var(--shadow-large); }
        .form-group { margin-bottom: 35px; position: relative; }
        .form-group input, .form-group textarea { width: 100%; padding: 15px 10px 10px 10px; border: none; border-bottom: 2px solid var(--beige-rose); font-family: var(--font-texte); font-size: 1rem; transition: border-color 0.3s ease; background-color: transparent; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .form-group label { position: absolute; top: 15px; left: 10px; color: #999; transition: all 0.3s ease; pointer-events: none; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: var(--dore); }
        .form-group input:focus + label, .form-group input:not(:placeholder-shown) + label,
        .form-group textarea:focus + label, .form-group textarea:not(:placeholder-shown) + label { top: -10px; left: 5px; font-size: 0.8rem; color: var(--dore); }

        /* --------------------------------- */
        /* --- FOOTER --- */
        /* --------------------------------- */
        .main-footer { background-color: var(--gris-anthracite); color: var(--creme); padding: 60px 0 40px 0; }
        .footer-content { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 40px; margin-bottom: 40px; text-align: left; }
        .footer-column { flex: 1; min-width: 200px; }
        .footer-column h4 { font-family: var(--font-titre); color: white; font-size: 1.2rem; margin-bottom: 20px; border-bottom: 1px solid var(--dore); padding-bottom: 10px; display: inline-block; }
        .footer-column p, .footer-column a { color: var(--beige-rose); text-decoration: none; display: block; margin-bottom: 10px; transition: color 0.3s ease; }
        .footer-column a:hover { color: white; }
        .social-icons a { display: inline-block; margin-right: 15px; font-size: 1.5rem; }
        .footer-bottom { text-align: center; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); font-size: 0.9rem; color: var(--beige-rose); }

        /* --------------------------------- */
        /* --- RESPONSIVE & MENU BURGER --- */
        /* --------------------------------- */
        .burger-menu { display: none; width: 30px; height: 22px; position: relative; cursor: pointer; z-index: 1002; }
        .burger-menu span { display: block; position: absolute; height: 3px; width: 100%; background: var(--gris-anthracite); border-radius: 3px; left: 0; transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out; }
        .burger-menu span:nth-child(1) { top: 0px; } .burger-menu span:nth-child(2) { top: 9px; } .burger-menu span:nth-child(3) { top: 18px; }
        .mobile-nav-open .burger-menu span:nth-child(1) { top: 9px; transform: rotate(135deg); }
        .mobile-nav-open .burger-menu span:nth-child(2) { opacity: 0; transform: translateX(-20px); }
        .mobile-nav-open .burger-menu span:nth-child(3) { top: 9px; transform: rotate(-135deg); }
        .mobile-nav { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: var(--creme); display: flex; flex-direction: column; justify-content: center; align-items: center; transform: translateX(100%); transition: transform 0.5s var(--ease-out-cubic); z-index: 1001; }
        .mobile-nav-open .mobile-nav { transform: translateX(0); }
        .mobile-nav a { font-size: 1.5rem; font-family: var(--font-titre); color: var(--gris-anthracite); text-decoration: none; margin: 15px 0; opacity: 0; transform: translateY(20px); }
        .mobile-nav-open .mobile-nav a { animation: mobileLinkFade 0.5s var(--ease-out-cubic) forwards; }
        .mobile-nav a:nth-child(1) { animation-delay: 0.2s; } .mobile-nav a:nth-child(2) { animation-delay: 0.25s; } .mobile-nav a:nth-child(3) { animation-delay: 0.3s; } .mobile-nav a:nth-child(4) { animation-delay: 0.35s; } .mobile-nav a:nth-child(5) { animation-delay: 0.4s; } .mobile-nav a:nth-child(6) { animation-delay: 0.45s; } .mobile-nav a:nth-child(7) { animation-delay: 0.5s; }
        @keyframes mobileLinkFade { to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 1024px) {
            .main-nav { display: none; }
            .burger-menu { display: block; }
            #a-propos .container { flex-direction: column; }
        }
        
        @media (max-width: 768px) {
            h2 { font-size: 2.2rem; }
            section { padding: 80px 0; }
            .hero-content h1 { font-size: 2.8rem; }
            #a-propos::before { display: none; }
            .timeline::before, .timeline::after { left: 20px; }
            .timeline-item { width: 100%; padding-left: 60px; padding-right: 10px; text-align: left !important; }
            .timeline-item:nth-child(even) { left: 0%; }
            .timeline-item::after { left: 10px; }
            .footer-content { text-align: center; }
            .footer-column { text-align: center; }
            .footer-column h4 { display: inline-block; }
        }

        /* Lien vers l'administration */
        .admin-link {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--dore);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: var(--shadow-large);
            transition: transform 0.3s ease;
            z-index: 999;
        }
        .admin-link:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <!-- ======================= HEADER ======================= -->
    <header class="main-header">
        <div class="container">
            <a href="#accueil" class="logo"><?= htmlspecialchars($footerData['logo']) ?></a>
            <nav class="main-nav">
                <a href="#accueil">Accueil</a>
                <a href="#a-propos">À Propos</a>
                <a href="#pour-qui">Pour Qui ?</a>
                <a href="#le-bilan">Le Bilan</a>
                <a href="#benefices">Bénéfices</a>
                <a href="#faq">FAQ</a>
                <a href="#contact">Contact</a>
            </nav>
            <div class="burger-menu">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </header>
    
    <div class="mobile-nav">
        <a href="#accueil">Accueil</a>
        <a href="#a-propos">À Propos</a>
        <a href="#pour-qui">Pour Qui ?</a>
        <a href="#le-bilan">Le Bilan</a>
        <a href="#benefices">Bénéfices</a>
        <a href="#faq">FAQ</a>
        <a href="#contact">Contact</a>
    </div>

    <!-- ======================= MAIN CONTENT ======================= -->
    <main>

        <!-- SECTION ACCUEIL -->
        <section id="accueil">
            <div class="hero-content">
                <h1 class="animated-title"><?= $heroData['title'] ?></h1>
                <p class="fade-in" style="transition-delay: 0.5s;"><?= htmlspecialchars($heroData['subtitle']) ?></p>
                <a href="#contact" class="cta-button fade-in" style="transition-delay: 0.7s;"><?= htmlspecialchars($heroData['cta_text']) ?></a>
            </div>
        </section>

        <!-- SECTION À PROPOS -->
        <section id="a-propos">
            <div class="container">
                <div class="about-image fade-in">
                    <img src="<?= htmlspecialchars($aboutData['image']) ?>" alt="Portrait professionnel de la coach">
                </div>
                <div class="about-text fade-in" style="transition-delay: 0.2s;">
                    <h2><?= htmlspecialchars($aboutData['title']) ?></h2>
                    <h3><?= htmlspecialchars($aboutData['subtitle']) ?></h3>
                    <p><?= htmlspecialchars($aboutData['description1']) ?></p>
                    <p><?= htmlspecialchars($aboutData['description2']) ?></p>
                </div>
            </div>
        </section>
        
        <!-- SECTION "POUR QUI ?" -->
        <section id="pour-qui">
             <div class="container">
                <h2 class="fade-in">Est-ce que cet accompagnement est fait pour vous ?</h2>
                <p class="section-subtitle fade-in">Si vous vous reconnaissez dans l'une de ces situations, alors la réponse est probablement oui.</p>
                <div class="grid-layout">
                    <div class="card fade-in">
                        <h3><span class="icon"><i class="fa-solid fa-compass"></i></span>En reconversion</h3>
                        <p>Vous avez une idée mais n'osez pas sauter le pas, ou au contraire, vous êtes dans le flou total et cherchez une nouvelle voie.</p>
                    </div>
                    <div class="card fade-in" style="transition-delay: 0.2s;">
                        <h3><span class="icon"><i class="fa-solid fa-arrow-trend-up"></i></span>En quête d'évolution</h3>
                        <p>Vous vous sentez à l'étroit dans votre poste actuel et souhaitez évoluer, mais ne savez pas comment valoriser vos compétences.</p>
                    </div>
                    <div class="card fade-in" style="transition-delay: 0.4s;">
                        <h3><span class="icon"><i class="fa-solid fa-seedling"></i></span>En manque de sens</h3>
                        <p>Votre travail ne vous passionne plus. Vous cherchez à aligner votre vie professionnelle avec vos valeurs personnelles.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION LE BILAN -->
        <section id="le-bilan">
            <div class="container">
                <div class="fade-in">
                    <h2>Un cheminement en 3 phases clés</h2>
                    <p class="section-subtitle fade-in">Le bilan est un voyage structuré que nous faisons ensemble. Loin d'être un simple test, c'est un dialogue constructif pour co-créer votre avenir.</p>
                </div>

                <div class="timeline fade-in">
                    <div class="timeline-item fade-in">
                        <div class="timeline-content">
                            <h3>Phase 1 : L'Investigation</h3>
                            <p>Nous analysons votre parcours, vos expériences, mais surtout vos envies profondes pour comprendre qui vous êtes et ce qui vous motive.</p>
                        </div>
                    </div>
                    <div class="timeline-item fade-in">
                        <div class="timeline-content">
                            <h3>Phase 2 : L'Exploration</h3>
                            <p>Nous explorons les pistes professionnelles possibles, nous enquêtons sur les métiers et les formations pour construire un projet réaliste.</p>
                        </div>
                    </div>
                    <div class="timeline-item fade-in">
                        <div class="timeline-content">
                            <h3>Phase 3 : La Construction</h3>
                            <p>Vous repartez avec un plan d'action clair, des étapes définies et une synthèse écrite pour mettre en œuvre votre projet en toute confiance.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- SECTION "BÉNÉFICES" -->
        <section id="benefices">
            <div class="container">
                <h2 class="fade-in">Les bénéfices concrets de notre collaboration</h2>
                <div class="grid-layout" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
                    <div class="benefit-card card fade-in">
                        <span class="icon"><i class="fa-solid fa-lightbulb"></i></span>
                        <h3>Clarté & Vision</h3>
                        <p>Repartez avec un projet professionnel clair, défini et qui vous ressemble.</p>
                    </div>
                    <div class="benefit-card card fade-in" style="transition-delay: 0.1s;">
                        <span class="icon"><i class="fa-solid fa-mountain-sun"></i></span>
                        <h3>Confiance Retrouvée</h3>
                        <p>Prenez conscience de vos forces, de vos talents et de votre valeur unique.</p>
                    </div>
                    <div class="benefit-card card fade-in" style="transition-delay: 0.2s;">
                        <span class="icon"><i class="fa-solid fa-map-signs"></i></span>
                        <h3>Plan d'Action</h3>
                        <p>Obtenez une feuille de route précise et des étapes concrètes pour vos objectifs.</p>
                    </div>
                    <div class="benefit-card card fade-in" style="transition-delay: 0.3s;">
                        <span class="icon"><i class="fa-solid fa-heart"></i></span>
                        <h3>Sens & Épanouissement</h3>
                        <p>Alignez enfin votre carrière avec ce qui compte vraiment pour vous.</p>
                    </div>
                    <div class="benefit-card card fade-in" style="transition-delay: 0.4s;">
                        <span class="icon"><i class="fa-solid fa-toolbox"></i></span>
                        <h3>Outils Personnalisés</h3>
                        <p>Disposez d'outils sur-mesure pour continuer à piloter votre carrière en autonomie.</p>
                    </div>
                    <div class="benefit-card card fade-in" style="transition-delay: 0.5s;">
                        <span class="icon"><i class="fa-solid fa-network-wired"></i></span>
                        <h3>Réseau & Opportunités</h3>
                        <p>Apprenez à développer votre réseau et à identifier les bonnes opportunités.</p>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- SECTION "FAQ" -->
        <section id="faq">
            <div class="container">
                <h2 class="fade-in">Vos questions, nos réponses</h2>
                <p class="section-subtitle fade-in">Voici les réponses aux questions les plus fréquentes pour vous aider à y voir plus clair.</p>
                <div class="faq-accordion">
                    <div class="faq-item fade-in">
                        <div class="faq-question">Combien de temps dure un bilan de compétences ?</div>
                        <div class="faq-answer">
                            <p>Un bilan de compétences dure généralement jusqu'à 24 heures, réparties sur plusieurs semaines. Cela nous laisse le temps d'approfondir chaque étape sans se presser, avec des séances de 2 à 3 heures.</p>
                        </div>
                    </div>
                    <div class="faq-item fade-in" style="transition-delay: 0.2s;">
                        <div class="faq-question">Mon bilan est-il finançable par le CPF ?</div>
                        <div class="faq-answer">
                            <p>Oui, absolument. Le bilan de compétences est une formation éligible au Compte Personnel de Formation (CPF). Je vous accompagnerai dans les démarches pour utiliser vos droits et financer votre accompagnement.</p>
                        </div>
                    </div>
                    <div class="faq-item fade-in" style="transition-delay: 0.4s;">
                        <div class="faq-question">Les séances peuvent-elles se faire à distance ?</div>
                        <div class="faq-answer">
                            <p>Oui, je propose des accompagnements en présentiel dans mon cabinet à Paris, mais également 100% à distance par visioconférence. Nous choisissons ensemble la formule qui vous convient le mieux.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION CONTACT -->
        <section id="contact">
            <div class="container">
                <div class="fade-in">
                    <h2><?= htmlspecialchars($contactData['title']) ?></h2>
                    <p class="section-subtitle fade-in"><?= htmlspecialchars($contactData['subtitle']) ?></p>
                </div>

                <?php if (isset($contactSuccess)): ?>
                    <div class="contact-form fade-in">
                        <div class="text-center">
                            <i class="fas fa-check-circle text-green-600 text-4xl mb-4"></i>
                            <h3 class="text-2xl font-bold text-green-600 mb-2">Message envoyé !</h3>
                            <p class="text-gray-600">Merci pour votre message. Je vous répondrai dans les plus brefs délais.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <form action="#contact" method="POST" class="contact-form fade-in">
                        <input type="hidden" name="contact_form" value="1">
                        <div class="form-group">
                            <input type="text" id="name" name="name" required placeholder=" ">
                            <label for="name">Votre Nom</label>
                        </div>
                        <div class="form-group">
                            <input type="email" id="email" name="email" required placeholder=" ">
                            <label for="email">Votre Email</label>
                        </div>
                        <div class="form-group">
                            <textarea id="message" name="message" required placeholder=" "></textarea>
                            <label for="message">Votre Message</label>
                        </div>
                        <div style="text-align: center;">
                            <button type="submit" class="cta-button">Envoyer le message</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </section>

    </main>

    <!-- ======================= FOOTER ======================= -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h4>Navigation</h4>
                    <a href="#accueil">Accueil</a>
                    <a href="#a-propos">À Propos</a>
                    <a href="#le-bilan">Le Bilan</a>
                    <a href="#faq">FAQ</a>
                    <a href="#contact">Contact</a>
                </div>
                <div class="footer-column">
                    <h4>Coordonnées</h4>
                    <p><?= $footerData['address'] ?></p>
                    <a href="mailto:<?= htmlspecialchars($footerData['email']) ?>"><?= htmlspecialchars($footerData['email']) ?></a>
                    <a href="tel:<?= htmlspecialchars(str_replace(' ', '', $footerData['phone'])) ?>"><?= htmlspecialchars($footerData['phone']) ?></a>
                </div>
                <div class="footer-column">
                    <h4>Suivez-moi</h4>
                    <div class="social-icons">
                        <a href="<?= htmlspecialchars($footerData['social_linkedin']) ?>" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
                        <a href="<?= htmlspecialchars($footerData['social_instagram']) ?>" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= htmlspecialchars($footerData['copyright']) ?> | <a href="#">Mentions Légales</a></p>
            </div>
        </div>
    </footer>

    <!-- Lien vers l'administration -->
    <a href="admin/" class="admin-link" title="Administration">
        <i class="fas fa-cog"></i>
    </a>

    <!-- ======================= SCRIPT JAVASCRIPT ======================= -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {

            // --- Animation du titre principal ---
            const animatedTitle = document.querySelector('.hero-content h1');
            if(animatedTitle) {
                const text = animatedTitle.innerHTML; // Utiliser innerHTML pour préserver les balises <br>
                const chars = text.split('');
                animatedTitle.innerHTML = '';
                chars.forEach((char, i) => {
                    if (char === '<') {
                        // Handle HTML tags like <br>
                        let tag = '';
                        let j = i;
                        while (j < chars.length && chars[j] !== '>') {
                            tag += chars[j];
                            j++;
                        }
                        tag += chars[j]; // Add the closing >
                        animatedTitle.innerHTML += tag;
                        i = j; // Skip the characters we just processed
                    } else {
                        const span = document.createElement('span');
                        span.className = 'char';
                        span.innerHTML = char === ' ' ? '&nbsp;' : char;
                        span.style.transitionDelay = `${i * 0.03}s`;
                        animatedTitle.appendChild(span);
                    }
                });
                // Trigger animation
                setTimeout(() => {
                    document.querySelectorAll('.hero-content h1 .char').forEach(span => {
                        span.style.opacity = '1';
                        span.style.transform = 'translateY(0) rotate(0)';
                    });
                }, 100);
            }

            // --- Gestion du header au scroll (hide/show) ---
            let lastScrollTop = 0;
            const header = document.querySelector('.main-header');
            window.addEventListener('scroll', () => {
                let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                if (scrollTop > lastScrollTop && scrollTop > 200) {
                    header.classList.add('hidden');
                } else {
                    header.classList.remove('hidden');
                }
                lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;

                if (window.scrollY > 50) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });

            // --- Animation d'apparition au scroll ---
            const observerOptions = { threshold: 0.15 };
            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);
            document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));
            
            const timelineObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });
            document.querySelectorAll('.timeline').forEach(el => timelineObserver.observe(el));

            // --- Surlignage du lien de navigation actif ---
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('.main-nav a');
            const sectionObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        navLinks.forEach(link => {
                            link.classList.remove('active');
                            if (link.getAttribute('href').substring(1) === entry.target.id) {
                                link.classList.add('active');
                            }
                        });
                    }
                });
            }, { rootMargin: "-40% 0px -60% 0px" });
            sections.forEach(section => {
                 sectionObserver.observe(section)
            });

            // --- Gestion du menu burger ---
            const burgerMenu = document.querySelector('.burger-menu');
            const mobileNavLinks = document.querySelectorAll('.mobile-nav a');
            
            burgerMenu.addEventListener('click', () => {
                document.body.classList.toggle('mobile-nav-open');
            });

            mobileNavLinks.forEach(link => {
                link.addEventListener('click', () => {
                    document.body.classList.remove('mobile-nav-open');
                });
            });
            
            // --- Gestion de l'accordéon FAQ ---
            const faqItems = document.querySelectorAll('.faq-item');
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question');
                question.addEventListener('click', () => {
                    const currentlyActive = document.querySelector('.faq-item.active');
                    if (currentlyActive && currentlyActive !== item) {
                        currentlyActive.classList.remove('active');
                    }
                    item.classList.toggle('active');
                });
            });

        });
    </script>
</body>
</html>
