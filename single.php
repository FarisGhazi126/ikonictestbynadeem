<?php get_header(); ?>
<main class="container section two-col-layout">
    <section>
        <?php while (have_posts()) : the_post(); ?>
            <article class="card single-card">
                <h1><?php the_title(); ?></h1>
                <p class="meta"><?php echo esc_html(get_the_date()); ?></p>
                <?php the_content(); ?>
            </article>
        <?php endwhile; ?>
    </section>
    <?php get_sidebar(); ?>
</main>
<?php get_footer(); ?>
