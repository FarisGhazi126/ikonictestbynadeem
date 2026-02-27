<?php get_header(); ?>
<main class="container section">
    <?php while (have_posts()) : the_post(); ?>
        <article class="card single-card">
            <h1><?php the_title(); ?></h1>
            <?php the_content(); ?>
        </article>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
