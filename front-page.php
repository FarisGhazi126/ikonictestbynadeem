<?php get_header(); ?>
<main>
    <section class="hero">
        <div class="container hero-grid">
            <div>
                <p class="eyebrow"><?php esc_html_e('Welcome to Agrova', 'ikonic-test'); ?></p>
                <h1><?php echo esc_html(get_theme_mod('ikonic_hero_title', 'The Leading Modern Agriculture & Organic Market')); ?></h1>
                <p><?php echo esc_html(get_theme_mod('ikonic_hero_text', 'Discover complete agriculture consulting, production strategy and crop optimization designed for modern farms.')); ?></p>
                <div class="hero-actions">
                    <a class="btn" href="#services"><?php esc_html_e('Explore Services', 'ikonic-test'); ?></a>
                    <a class="btn btn-outline" href="#contact"><?php esc_html_e('Contact Us', 'ikonic-test'); ?></a>
                </div>
            </div>
            <div class="hero-card card">
                <img src="https://pixelaxis.net/agrova/wp-content/uploads/2024/11/h1-hero-img-01.jpg" alt="Farmer">
                <h3><?php esc_html_e('Fresh & Healthy Food', 'ikonic-test'); ?></h3>
                <p><?php esc_html_e('Sustainable process for quality farming and business growth.', 'ikonic-test'); ?></p>
            </div>
        </div>
    </section>

    <section class="highlights">
        <div class="container highlight-grid">
            <article><h3>100% <?php esc_html_e('Organic Foods', 'ikonic-test'); ?></h3></article>
            <article><h3>285+ <?php esc_html_e('Happy Customers', 'ikonic-test'); ?></h3></article>
            <article><h3>25+ <?php esc_html_e('Years Experience', 'ikonic-test'); ?></h3></article>
            <article><h3>50 <?php esc_html_e('Team Members', 'ikonic-test'); ?></h3></article>
        </div>
    </section>

    <section class="section" id="services">
        <div class="container">
            <p class="eyebrow center"><?php esc_html_e('Our Services', 'ikonic-test'); ?></p>
            <h2 class="center"><?php esc_html_e('Consulting to elevate your agriculture business', 'ikonic-test'); ?></h2>
            <div class="grid services-grid">
                <article class="card"><h3><?php esc_html_e('Agricultural Consultancy', 'ikonic-test'); ?></h3><p><?php esc_html_e('Professional planning for crop cycles, soil care and irrigation systems.', 'ikonic-test'); ?></p></article>
                <article class="card"><h3><?php esc_html_e('Modern Farming', 'ikonic-test'); ?></h3><p><?php esc_html_e('Introduce technology-driven workflows and improve productivity.', 'ikonic-test'); ?></p></article>
                <article class="card"><h3><?php esc_html_e('Organic Solution', 'ikonic-test'); ?></h3><p><?php esc_html_e('Natural, sustainable methods to increase market-ready harvest.', 'ikonic-test'); ?></p></article>
            </div>
        </div>
    </section>

    <section class="section section-alt">
        <div class="container two-col">
            <img class="about-img" src="https://pixelaxis.net/agrova/wp-content/uploads/2024/11/h1-about-img-01.jpg" alt="About Agrova">
            <div>
                <p class="eyebrow"><?php esc_html_e('About Company', 'ikonic-test'); ?></p>
                <h2><?php echo esc_html(get_theme_mod('ikonic_about_title', 'Discover why agriculture consulting is needed for modern farming')); ?></h2>
                <p><?php echo esc_html(get_theme_mod('ikonic_about_text', 'We help agri-business owners improve operations, monitor crop quality and scale with confidence.')); ?></p>
                <a class="btn" href="#projects"><?php esc_html_e('Learn More', 'ikonic-test'); ?></a>
            </div>
        </div>
    </section>

    <section class="section" id="projects">
        <div class="container">
            <p class="eyebrow center"><?php esc_html_e('Latest Projects', 'ikonic-test'); ?></p>
            <h2 class="center"><?php esc_html_e('Our Recent Agriculture Case Studies', 'ikonic-test'); ?></h2>
            <div class="grid">
                <?php
                $projects_query = new WP_Query(array('post_type' => 'projects', 'posts_per_page' => 3));
                if ($projects_query->have_posts()) :
                    while ($projects_query->have_posts()) : $projects_query->the_post();
                        ?>
                        <article class="card">
                            <?php if (has_post_thumbnail()) : the_post_thumbnail('medium_large'); endif; ?>
                            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            <p><?php echo esc_html(get_post_meta(get_the_ID(), 'project_description', true)); ?></p>
                        </article>
                        <?php
                    endwhile;
                    wp_reset_postdata();
                else : ?>
                    <article class="card"><h3><?php esc_html_e('No projects found', 'ikonic-test'); ?></h3><p><?php esc_html_e('Add project entries from dashboard to populate this section.', 'ikonic-test'); ?></p></article>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="section section-alt" id="contact">
        <div class="container two-col">
            <div>
                <p class="eyebrow"><?php esc_html_e('Contact us', 'ikonic-test'); ?></p>
                <h2><?php esc_html_e('Need agriculture consulting? Send us a message', 'ikonic-test'); ?></h2>
                <p><strong><?php esc_html_e('Phone:', 'ikonic-test'); ?></strong> <?php echo esc_html(get_theme_mod('ikonic_contact_phone', '+1 (307) 555-0133')); ?></p>
                <p><strong><?php esc_html_e('Email:', 'ikonic-test'); ?></strong> <?php echo esc_html(get_theme_mod('ikonic_contact_email', 'hello@example.com')); ?></p>
            </div>
            <div class="card form-placeholder">
                <p><?php esc_html_e('Add your Contact Form 7 or WPForms shortcode here in production.', 'ikonic-test'); ?></p>
                <p><code><?php esc_html_e('[contact-form-7 id="123" title="Contact"]', 'ikonic-test'); ?></code></p>
            </div>
        </div>
    </section>
</main>
<?php get_footer(); ?>
