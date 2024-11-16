<?php get_header(); ?>

<main>
    <section class="home-intro">
        <h1>Welcome to Our Projects Showcase!</h1>
        <p>Explore our latest and most exciting projects. Get to know our work, timeline, and innovations.</p>
    </section>

    <section class="home-projects">
        <h2>Featured Projects</h2>
        <?php
        // Fetch and display 3 most recent projects
        $args = array(
            'post_type' => 'projects',
            'posts_per_page' => 3, // Display 3 projects
        );
        $projects_query = new WP_Query($args);

        if ($projects_query->have_posts()) :
            while ($projects_query->have_posts()) : $projects_query->the_post();
        ?>
                <article class="project">
                    <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                    <p><strong>Start Date:</strong> <?php echo get_post_meta(get_the_ID(), 'project_start_date', true); ?></p>
                    <p><a href="<?php the_permalink(); ?>">View Project Details</a></p>
                </article>
        <?php
            endwhile;
            wp_reset_postdata(); // Don't forget to reset the query
        else :
            echo '<p>No featured projects found.</p>';
        endif;
        ?>
    </section>

    <section class="home-contact">
        <h2>Get in Touch</h2>
        <p>If you’re interested in collaborating on a project, feel free to contact us.</p>
        <a href="contact-page-link" class="btn">Contact Us</a>
    </section>
</main>

<?php get_footer(); ?>
