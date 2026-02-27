<?php get_header(); ?>
<main class="container section two-col-layout">
    <section>
        <h1><?php the_archive_title(); ?></h1>
        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
            <article class="card post-card">
                <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                <p><?php the_excerpt(); ?></p>
            </article>
        <?php endwhile; the_posts_pagination(); else : ?>
            <p><?php esc_html_e('Nothing found.', 'ikonic-test'); ?></p>
        <?php endif; ?>
    </section>
    <?php get_sidebar(); ?>
</main>
<?php get_footer(); ?>
