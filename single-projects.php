<?php get_header(); ?>
<main class="container section">
    <?php while (have_posts()) : the_post(); ?>
        <article class="card single-card">
            <h1><?php the_title(); ?></h1>
            <p><strong><?php esc_html_e('Start Date:', 'ikonic-test'); ?></strong> <?php echo esc_html(get_post_meta(get_the_ID(), 'project_start_date', true)); ?></p>
            <p><strong><?php esc_html_e('End Date:', 'ikonic-test'); ?></strong> <?php echo esc_html(get_post_meta(get_the_ID(), 'project_end_date', true)); ?></p>
            <p><strong><?php esc_html_e('Project URL:', 'ikonic-test'); ?></strong>
                <a href="<?php echo esc_url(get_post_meta(get_the_ID(), 'project_url', true)); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Visit Project', 'ikonic-test'); ?></a>
            </p>
            <div><?php the_content(); ?></div>
        </article>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
