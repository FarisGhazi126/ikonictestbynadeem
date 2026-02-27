<?php get_header(); ?>

<main>
    <section class="hero">
        <div class="container">
            <p class="eyebrow"><?php esc_html_e('Welcome to Agrova Style Theme', 'ikonic-test'); ?></p>
            <h1><?php echo esc_html(get_theme_mod('ikonic_hero_title')); ?></h1>
            <p><?php echo esc_html(get_theme_mod('ikonic_hero_text')); ?></p>
            <a class="btn" href="<?php echo esc_url(get_post_type_archive_link('projects')); ?>"><?php esc_html_e('Explore Projects', 'ikonic-test'); ?></a>
        </div>
    </section>

    <section class="section">
        <div class="container two-col">
            <div>
                <h2><?php echo esc_html(get_theme_mod('ikonic_about_title')); ?></h2>
                <p><?php echo esc_html(get_theme_mod('ikonic_about_text')); ?></p>
            </div>
            <div class="card">
                <h3><?php esc_html_e('Contact', 'ikonic-test'); ?></h3>
                <p><strong><?php esc_html_e('Phone:', 'ikonic-test'); ?></strong> <?php echo esc_html(get_theme_mod('ikonic_contact_phone')); ?></p>
                <p><strong><?php esc_html_e('Email:', 'ikonic-test'); ?></strong> <?php echo esc_html(get_theme_mod('ikonic_contact_email')); ?></p>
            </div>
        </div>
    </section>

    <section class="section section-alt">
        <div class="container">
            <h2><?php esc_html_e('Featured Projects', 'ikonic-test'); ?></h2>
            <div class="grid">
                <?php
                $projects_query = new WP_Query(
                    array(
                        'post_type'      => 'projects',
                        'posts_per_page' => 3,
                    )
                );
                if ($projects_query->have_posts()) :
                    while ($projects_query->have_posts()) :
                        $projects_query->the_post();
                        ?>
                        <article class="card">
                            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            <p><?php echo esc_html(get_post_meta(get_the_ID(), 'project_description', true)); ?></p>
                            <a href="<?php the_permalink(); ?>"><?php esc_html_e('Read more', 'ikonic-test'); ?></a>
                        </article>
                        <?php
                    endwhile;
                    wp_reset_postdata();
                else :
                    ?>
                    <p><?php esc_html_e('No projects yet. Add your first project from Dashboard → Projects.', 'ikonic-test'); ?></p>
                    <?php
                endif;
                ?>
            </div>
        </div>
    </section>
</main>

<?php get_footer(); ?>
