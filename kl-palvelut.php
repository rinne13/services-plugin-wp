<?php
/**
 * Plugin Name: KL Palvelut
 * Description: Custom post type "Palvelut" + метаполя, таксономия и шорткод для главной.
 * Author: Varvara
 * Version: 1.0.0
 * Text Domain: koiranloma
 */

if ( ! defined('ABSPATH') ) exit;

class KL_Palvelut {
    // Имена сущностей
    const POST_TYPE   = 'palvelu';
    const TAX         = 'palvelu_kategoria';

    // Метаполя
    const META_PRICE   = '_kl_price';      // строка "60,- / yö"
    const META_BULLETS = '_kl_bullets';    // массив из трёх строк
    const META_HOME    = '_kl_show_on_home';
    const META_CTA_TXT = '_kl_cta_text';
    const META_CTA_URL = '_kl_cta_url';

    public function __construct() {
        add_action('init', [$this, 'register_cpt']);
        add_action('init', [$this, 'register_tax']);

        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_meta'], 10, 2);

        // колонки в админке
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'cols']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'col_content'], 10, 2);

        // архив /palvelut показываем ровно 3 (по порядку)
        add_action('pre_get_posts', [$this, 'limit_archive_to_three']);

        // шорткод для главной
        add_shortcode('palvelut_home', [$this, 'shortcode_home']);
    }

    /** Регистрация CPT */
    public function register_cpt() {
        $labels = [
            'name'               => __('Palvelut', 'koiranloma'),
            'singular_name'      => __('Palvelu', 'koiranloma'),
            'add_new'            => __('Lisää uusi', 'koiranloma'),
            'add_new_item'       => __('Lisää uusi palvelu', 'koiranloma'),
            'edit_item'          => __('Muokkaa palvelua', 'koiranloma'),
            'new_item'           => __('Uusi palvelu', 'koiranloma'),
            'view_item'          => __('Näytä palvelu', 'koiranloma'),
            'search_items'       => __('Etsi palveluja', 'koiranloma'),
            'not_found'          => __('Ei palveluja', 'koiranloma'),
            'not_found_in_trash' => __('Ei palveluja roskakorissa', 'koiranloma'),
            'all_items'          => __('Kaikki palvelut', 'koiranloma'),
            'menu_name'          => __('Palvelut', 'koiranloma'),
        ];

        register_post_type(self::POST_TYPE, [
            'labels'        => $labels,
            'public'        => true,
            'show_in_rest'  => false, // классический редактор
            'menu_icon'     => 'dashicons-pets',
            'supports'      => ['title','editor','excerpt','thumbnail','page-attributes'],
            'has_archive'   => true,
            'rewrite'       => ['slug' => 'palvelut', 'with_front' => false],
        ]);
    }

    /** Таксономия категорий услуг */
    public function register_tax() {
        $labels = [
            'name'          => __('Kategoriat', 'koiranloma'),
            'singular_name' => __('Kategoria', 'koiranloma'),
        ];
        register_taxonomy(self::TAX, [self::POST_TYPE], [
            'labels'        => $labels,
            'hierarchical'  => true,
            'public'        => true,
            'show_in_rest'  => false,
            'rewrite'       => ['slug' => 'palvelu-kategoria', 'with_front' => false],
        ]);
    }

    /** Метабокс */
    public function add_metabox() {
        add_meta_box(
            'kl_palvelu_meta',
            __('Palvelun tiedot', 'koiranloma'),
            [$this, 'render_metabox'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_metabox($post) {
        wp_nonce_field('kl_palvelu_save', 'kl_palvelu_nonce');

        $price   = get_post_meta($post->ID, self::META_PRICE, true);
        $bullets = (array) get_post_meta($post->ID, self::META_BULLETS, true);
        $show    = get_post_meta($post->ID, self::META_HOME, true);
        $cta_txt = get_post_meta($post->ID, self::META_CTA_TXT, true);
        $cta_url = get_post_meta($post->ID, self::META_CTA_URL, true);

        for ($i=0; $i<3; $i++) if (!isset($bullets[$i])) $bullets[$i] = '';
        ?>
        <style>
          .kl-fields { display:grid; gap:16px; grid-template-columns: 1fr 1fr; }
          .kl-field { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:12px; }
          .kl-field h4 { margin:0 0 8px; }
          @media (max-width: 1200px){ .kl-fields { grid-template-columns:1fr; } }
        </style>

        <div class="kl-fields">
          <div class="kl-field">
            <h4><?php _e('Hinta (näytetään kortilla)', 'koiranloma'); ?></h4>
            <input type="text" name="kl_price" value="<?php echo esc_attr($price); ?>" placeholder="esim. 60,- tai €60" style="width:100%;"/>
            <p class="description"><?php _e('Vapaa teksti. Voit käyttää muotoa “60,-” tai “€60”.', 'koiranloma'); ?></p>

            <h4 style="margin-top:12px;"><?php _e('Bullets (3 kpl)', 'koiranloma'); ?></h4>
            <?php for ($i=0; $i<3; $i++): ?>
              <input type="text" name="kl_bullets[]" value="<?php echo esc_attr($bullets[$i]); ?>"
                     placeholder="<?php printf(__('Bullet %d', 'koiranloma'), $i+1); ?>"
                     style="width:100%;" />
            <?php endfor; ?>

            <h4 style="margin-top:12px;"><?php _e('Etusivu', 'koiranloma'); ?></h4>
            <label>
              <input type="checkbox" name="kl_show_on_home" value="1" <?php checked($show, '1'); ?> />
              <?php _e('Näytä tämä palvelu etusivulla (max 3 kpl)', 'koiranloma'); ?>
            </label>
          </div>

          <div class="kl-field">
            <h4><?php _e('Painike', 'koiranloma'); ?></h4>
            <label><?php _e('Teksti', 'koiranloma'); ?></label>
            <input type="text" name="kl_cta_text"
                   value="<?php echo esc_attr($cta_txt ?: __('Varaa aika','koiranloma')); ?>"
                   placeholder="<?php esc_attr_e('Varaa aika', 'koiranloma'); ?>" style="width:100%;">

            <label style="margin-top:8px; display:block;">
              <?php _e('Oma linkki (valinnainen). Jos tyhjä, käytetään WhatsApp-linkkiä asetuksista.', 'koiranloma'); ?>
            </label>
            <input type="url" name="kl_cta_url" value="<?php echo esc_url($cta_url); ?>" placeholder="https://..." style="width:100%;">

            <hr>
            <p class="description">
              <?php _e('Pääkuva asetetaan vain kohdassa “Featured image” oikealla. Galleriaa ei käytetä.', 'koiranloma'); ?>
            </p>
          </div>
        </div>
        <?php
    }

    /** Сохранение метаданных */
    public function save_meta($post_id, $post) {
        if ( ! isset($_POST['kl_palvelu_nonce']) || ! wp_verify_nonce($_POST['kl_palvelu_nonce'], 'kl_palvelu_save') ) return;
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( ! current_user_can('edit_post', $post_id) ) return;

        // Цена — свободный текст
        $price = isset($_POST['kl_price']) ? wp_kses_post(trim($_POST['kl_price'])) : '';
        update_post_meta($post_id, self::META_PRICE, $price);

        // Буллеты (3)
        $bullets = isset($_POST['kl_bullets']) && is_array($_POST['kl_bullets']) ? array_slice($_POST['kl_bullets'], 0, 3) : [];
        $bullets = array_map('sanitize_text_field', $bullets);
        update_post_meta($post_id, self::META_BULLETS, $bullets);

        // Флажок главной
        update_post_meta($post_id, self::META_HOME, isset($_POST['kl_show_on_home']) ? '1' : '');

        // CTA
        $cta_txt = isset($_POST['kl_cta_text']) ? sanitize_text_field($_POST['kl_cta_text']) : '';
        $cta_url = isset($_POST['kl_cta_url'])  ? esc_url_raw($_POST['kl_cta_url'])        : '';
        update_post_meta($post_id, self::META_CTA_TXT, $cta_txt);
        update_post_meta($post_id, self::META_CTA_URL, $cta_url);
    }

    /** Админ-колонки */
    public function cols($cols) {
        $new = [];
        $new['cb']        = $cols['cb'];
        $new['thumbnail'] = __('Kuva', 'koiranloma');
        $new['title']     = __('Nimi', 'koiranloma');
        $new['price']     = __('Hinta', 'koiranloma');
        $new['home']      = __('Etusivu', 'koiranloma');
        $new['date']      = $cols['date'];
        return $new;
    }

    public function col_content($col, $post_id) {
        if ($col === 'thumbnail') {
            if (has_post_thumbnail($post_id)) {
                echo get_the_post_thumbnail($post_id, [60,60], [
                    'style' => 'border-radius:6px;object-fit:cover;'
                ]);
            } else {
                echo '—';
            }
        }
        if ($col === 'price') {
            echo esc_html( get_post_meta($post_id, self::META_PRICE, true) );
        }
        if ($col === 'home') {
            echo get_post_meta($post_id, self::META_HOME, true) ? '✓' : '—';
        }
    }

    /** Ограничиваем архив /palvelut тремя карточками + порядок */
    public function limit_archive_to_three($q) {
        if ( is_admin() || ! $q->is_main_query() ) return;
        if ( $q->is_post_type_archive(self::POST_TYPE) ) {
            $q->set('posts_per_page', 3);
            $q->set('orderby', ['menu_order' => 'ASC', 'date' => 'DESC']);
            $q->set('order', 'ASC');
        }
    }

    /** Сервисный метод: WhatsApp-ссылка из настроек темы/контактов */
    private function whatsapp_link() {
        if (function_exists('koiranloma_get_contacts')) {
            $c = koiranloma_get_contacts();
            if (!empty($c['phone'])) {
                $digits = preg_replace('/[^0-9]/', '', $c['phone']);
                if ($digits) return 'https://wa.me/' . rawurlencode($digits);
            }
        }
        return '#';
    }

    /** Шорткод [palvelut_home limit="3"] — максимум 3 отмеченных на главную */
    public function shortcode_home($atts) {
        $atts = shortcode_atts(['limit'=>3], $atts, 'palvelut_home');

        $q = new WP_Query([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => intval($atts['limit']),
            'meta_key'       => self::META_HOME,
            'meta_value'     => '1',
            'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);

        if (!$q->have_posts()) return '';

        ob_start(); ?>
        <div class="services-grid">
          <?php while ($q->have_posts()): $q->the_post();
            $price   = get_post_meta(get_the_ID(), self::META_PRICE, true);
            $cta_txt = get_post_meta(get_the_ID(), self::META_CTA_TXT, true) ?: __('Varaa aika','koiranloma');
            $cta_url = get_post_meta(get_the_ID(), self::META_CTA_URL, true) ?: $this->whatsapp_link();

            // Excerpt: если заполнен вручную — сохраняем переносы; иначе короткая выжимка
            if ( has_excerpt() ) {
                $raw = get_the_excerpt();
                $excerpt_html = wpautop( esc_html( $raw ) );
            } else {
                $excerpt_html = wpautop( esc_html( wp_trim_words( wp_strip_all_tags( get_the_content() ), 40 ) ) );
            }
          ?>
            <article class="service-card">
              <div class="service-thumb" style="aspect-ratio:1/1; overflow:hidden;">
                <a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title()); ?>">
                  <?php if (has_post_thumbnail()) {
                      the_post_thumbnail('kl-service', [
                          'loading'=>'lazy',
                          'alt'=>esc_attr(get_the_title()),
                          'style'=>'width:100%;height:100%;object-fit:cover; '
                      ]);
                  } else {
                      echo '<img src="'.esc_url(get_stylesheet_directory_uri().'/assets/placeholder.jpg').'" alt="" style="width:100%;height:100%;object-fit:cover;">';
                  } ?>
                </a>
              </div>

              <h3 class="service-title">
                <a class="service-title__link" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
              </h3>

              <div class="service-excerpt"><?php echo $excerpt_html; ?></div>

              <?php if ($price): ?>
                <div style="display:flex;justify-content:center;align-items:baseline;gap:6px;margin:6px 0 10px;">
                  <div class="price-value" style="font-size:1.6rem;font-weight:800;color:var(--brand,#2c5f2d);"><?php echo esc_html($price); ?></div>
                </div>
              <?php endif; ?>

              <p style="text-align:center;margin:0 0 14px;">
                <a class="btn-cta" href="<?php echo esc_url($cta_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($cta_txt); ?></a>
              </p>
            </article>
          <?php endwhile; wp_reset_postdata(); ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

new KL_Palvelut();
