<?php get_header(); ?>

<main>
    <?php while (have_posts()) : the_post(); ?>
        <article class="project">
            <h2><?php the_title(); ?></h2>
            <p><strong>Start Date:</strong> <?php echo get_post_meta(get_the_ID(), 'project_start_date', true); ?></p>
            <p><strong>End Date:</strong> <?php echo get_post_meta(get_the_ID(), 'project_end_date', true); ?></p>
            <p><strong>Description:</strong> <?php the_content(); ?></p>
            <p><a href="<?php echo get_post_meta(get_the_ID(), 'project_url', true); ?>" target="_blank">Visit Project</a></p>
        </article>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>
