<?php get_header(); ?>

<main>
    <h1>Our Projects</h1>
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <article class="project">
                <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                <p><strong>Start Date:</strong> <?php echo get_post_meta(get_the_ID(), 'project_start_date', true); ?></p>
                <p><strong>End Date:</strong> <?php echo get_post_meta(get_the_ID(), 'project_end_date', true); ?></p>
            </article>
        <?php endwhile; ?>
    <?php else : ?>
        <p>No projects found.</p>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
