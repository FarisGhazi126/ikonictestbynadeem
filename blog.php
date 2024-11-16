<?php get_header(); ?>

<main>
    <section class="blog-posts">
        <h1>Latest Blog Posts</h1>
        <?php
        // Query for blog posts (if you have a Blog category or use Posts)
        if (have_posts()) :
            while (have_posts()) : the_post();
        ?>
                <article class="blog-post">
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <p><strong>Posted on:</strong> <?php echo get_the_date(); ?></p>
                    <p><?php the_excerpt(); ?></p>
                    <a href="<?php the_permalink(); ?>" class="btn">Read More</a>
                </article>
        <?php
            endwhile;
            // Pagination links
            the_posts_pagination(array(
                'prev_text' => 'Previous',
                'next_text' => 'Next',
            ));
        else :
            echo '<p>No blog posts found.</p>';
        endif;
        ?>
    </section>

    <section class="blog-sidebar">
        <h3>Archives</h3>
        <ul>
            <?php wp_get_archives(array('type' => 'monthly')); ?>
        </ul>
    </section>
</main>

<?php get_footer(); ?>
