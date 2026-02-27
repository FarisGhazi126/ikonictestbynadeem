<?php get_header(); ?>
<main class="container section">
    <h1><?php post_type_archive_title(); ?></h1>
    <div class="grid">
        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
            <article class="card">
                <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                <p><?php echo esc_html(get_post_meta(get_the_ID(), 'project_description', true)); ?></p>
                <p><strong><?php esc_html_e('Start:', 'ikonic-test'); ?></strong> <?php echo esc_html(get_post_meta(get_the_ID(), 'project_start_date', true)); ?></p>
            </article>
        <?php endwhile; else : ?>
            <p><?php esc_html_e('No projects found.', 'ikonic-test'); ?></p>
        <?php endif; ?>
    </div>
</main>
<?php get_footer(); ?>
