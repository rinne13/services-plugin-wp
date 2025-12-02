<?php
/**
 * Plugin Name: KL Palvelut
 * Description: Custom post type "Palvelut" + метаполя, таксономия и шорткод для главной (ровные карточки, выровненные ценники).
 * Author: Varvara
 * Version: 1.1.0
 * Text Domain: koiranloma
 */

if ( ! defined('ABSPATH') ) exit;

class KL_Palvelut {
    const POST_TYPE   = 'palvelu';
    const TAX         = 'palvelu_kategoria';
    const META_PRICE   = '_kl_price';
    const META_BULLETS = '_kl_bullets';
    const META_HOME    = '_kl_show_on_home';
    const META_CTA_TXT = '_kl_cta_text';
    const META_CTA_URL = '_kl_cta_url';

    public function __construct() {
        add_action('init', [$this, 'register_cpt']);
        add_action('init', [$this, 'register_tax']);
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_meta'], 10, 2);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'cols']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'col_content'], 10, 2);
        add_shortcode('palvelut_home', [$this, 'shortcode_home']);
    }

    public function register_cpt() {
        $labels = [
            'name'          => __('Palvelut', 'koiranloma'),
            'singular_name' => __('Palvelu', 'koiranloma'),
            'add_new'       => __('Lisää uusi', 'koiranloma'),
            'add_new_item'  => __('Lisää uusi palvelu', 'koiranloma'),
            'edit_item'     => __('Muokkaa palvelua', 'koiranloma'),
            'new_item'      => __('Uusi palvelu', 'koiranloma'),
            'view_item'     => __('Näytä palvelu', 'koiranloma'),
            'search_items'  => __('Etsi palveluja', 'koiranloma'),
            'not_found'     => __('Ei palveluja', 'koiranloma'),
            'menu_name'     => __('Palvelut', 'koiranloma'),
        ];
        register_post_type(self::POST_TYPE, [
            'labels'       => $labels,
            'public'       => true,
            'menu_icon'    => 'dashicons-pets',
            'supports'     => ['title','editor','excerpt','thumbnail','page-attributes'],
            'has_archive'  => true,
            'rewrite'      => ['slug'=>'palvelut','with_front'=>false],
        ]);
    }

    public function register_tax() {
        register_taxonomy(self::TAX, [self::POST_TYPE], [
            'labels'=>[
                'name'=>__('Kategoriat','koiranloma'),
                'singular_name'=>__('Kategoria','koiranloma'),
            ],
            'hierarchical'=>true,
            'public'=>true,
            'rewrite'=>['slug'=>'palvelu-kategoria','with_front'=>false],
        ]);
    }

    public function add_metabox() {
        add_meta_box('kl_palvelu_meta', __('Palvelun tiedot','koiranloma'),
            [$this,'render_metabox'], self::POST_TYPE, 'normal', 'high');
    }

    public function render_metabox($post) {
        wp_nonce_field('kl_palvelu_save', 'kl_palvelu_nonce');
        $price   = get_post_meta($post->ID, self::META_PRICE, true);
        $bullets = (array)get_post_meta($post->ID, self::META_BULLETS, true);
        $show    = get_post_meta($post->ID, self::META_HOME, true);
        $cta_txt = get_post_meta($post->ID, self::META_CTA_TXT, true);
        $cta_url = get_post_meta($post->ID, self::META_CTA_URL, true);
        for ($i=0; $i<3; $i++) if (!isset($bullets[$i])) $bullets[$i]='';
        ?>
        <style>
          .kl-fields{display:grid;gap:16px;grid-template-columns:1fr 1fr;}
          .kl-field{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px;}
          @media(max-width:1200px){.kl-fields{grid-template-columns:1fr;}}
        </style>
        <div class="kl-fields">
          <div class="kl-field">
            <h4><?php _e('Hinta','koiranloma'); ?></h4>
            <input type="text" name="kl_price" value="<?php echo esc_attr($price); ?>" style="width:100%;">
            <h4 style="margin-top:12px; "><?php _e('Bullets (3)','koiranloma'); ?></h4>
            <?php for($i=0;$i<3;$i++): ?>
              <input type="text" name="kl_bullets[]" value="<?php echo esc_attr($bullets[$i]); ?>" style="width:100%; ">
            <?php endfor; ?>
            <h4 style="margin-top:12px;"><?php _e('Etusivu','koiranloma'); ?></h4>
            <label><input type="checkbox" name="kl_show_on_home" value="1" <?php checked($show,'1'); ?>> <?php _e('Näytä etusivulla','koiranloma'); ?></label>
          </div>
          <div class="kl-field">
            <h4><?php _e('Painike','koiranloma'); ?></h4>
            <label><?php _e('Teksti','koiranloma'); ?></label>
            <input type="text" name="kl_cta_text" value="<?php echo esc_attr($cta_txt ?: __('Varaa aika','koiranloma')); ?>" style="width:100%;">
            <label style="margin-top:8px;display:block;"><?php _e('Oma linkki (valinnainen)','koiranloma'); ?></label>
            <input type="url" name="kl_cta_url" value="<?php echo esc_url($cta_url); ?>" style="width:100%;">
          </div>
        </div>
        <?php
    }

    public function save_meta($post_id) {
        if (!isset($_POST['kl_palvelu_nonce']) || !wp_verify_nonce($_POST['kl_palvelu_nonce'],'kl_palvelu_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post',$post_id)) return;

        update_post_meta($post_id,self::META_PRICE, sanitize_text_field($_POST['kl_price']??''));
        $bullets = isset($_POST['kl_bullets'])?array_slice($_POST['kl_bullets'],0,3):[];
        $bullets = array_map('sanitize_text_field',$bullets);
        update_post_meta($post_id,self::META_BULLETS,$bullets);
        update_post_meta($post_id,self::META_HOME, isset($_POST['kl_show_on_home'])?'1':'');
        update_post_meta($post_id,self::META_CTA_TXT, sanitize_text_field($_POST['kl_cta_text']??''));
        update_post_meta($post_id,self::META_CTA_URL, esc_url_raw($_POST['kl_cta_url']??''));
    }

    public function cols($cols){
        return [
            'cb'=>$cols['cb'],
            'thumbnail'=>__('Kuva','koiranloma'),
            'title'=>__('Nimi','koiranloma'),
            'price'=>__('Hinta','koiranloma'),
            'home'=>__('Etusivu','koiranloma'),
            'date'=>$cols['date']
        ];
    }
    public function col_content($col,$id){
        if($col==='thumbnail'){
            echo has_post_thumbnail($id)?get_the_post_thumbnail($id,[60,60],['style'=>'border-radius:6px;object-fit:cover;']):'—';
        }
        if($col==='price') echo esc_html(get_post_meta($id,self::META_PRICE,true));
        if($col==='home')  echo get_post_meta($id,self::META_HOME,true)?'✓':'—';
    }

    private function whatsapp_link(){
        if(function_exists('koiranloma_get_contacts')){
            $c=koiranloma_get_contacts();
            if(!empty($c['phone'])){
                $d=preg_replace('/[^0-9]/','',$c['phone']);
                if($d) return 'https://wa.me/'.rawurlencode($d);
            }
        }
        return '#';
    }

    /** Шорткод [palvelut_home] */
    public function shortcode_home($atts){
        $atts=shortcode_atts(['limit'=>3],$atts,'palvelut_home');
        $q=new WP_Query([
            'post_type'=>self::POST_TYPE,
            'posts_per_page'=>intval($atts['limit']),
            'meta_key'=>self::META_HOME,
            'meta_value'=>'1',
            'orderby'=>['menu_order'=>'ASC','date'=>'DESC'],
            'order'=>'ASC',
            'no_found_rows'=>true
        ]);
        if(!$q->have_posts())return'';

        ob_start(); ?>
        <div class="services-grid">
        <?php while($q->have_posts()):$q->the_post();
            $price=get_post_meta(get_the_ID(),self::META_PRICE,true);
            $cta_txt=get_post_meta(get_the_ID(),self::META_CTA_TXT,true)?:__('Varaa aika','koiranloma');
            $cta_url=get_post_meta(get_the_ID(),self::META_CTA_URL,true)?:$this->whatsapp_link();
            if(has_excerpt()){
                $excerpt_html=wpautop(esc_html(get_the_excerpt()));
            } else {
                $excerpt_html=wpautop(esc_html(wp_trim_words(wp_strip_all_tags(get_the_content()),40)));
            }
        ?>
          <article class="service-card">
            <div class="service-thumb" style="aspect-ratio:1/1;overflow:hidden;">
              <a href="<?php the_permalink(); ?>">
                <?php if(has_post_thumbnail()){
                    the_post_thumbnail('kl-service',['loading'=>'lazy','alt'=>esc_attr(get_the_title()),'style'=>'width:100%;height:100%;object-fit:cover;']);
                } else {
                    echo '<img src="'.esc_url(get_stylesheet_directory_uri().'/assets/placeholder.jpg').'" alt="" style="width:100%;height:100%;object-fit:cover;">';
                } ?>
              </a>
            </div>

            <h3 class="service-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
            <div class="service-excerpt"><?php echo $excerpt_html; ?></div>

            <div class="service-foot">
              <?php if($price): ?>
                <div class="price-value" ><?php echo esc_html($price); ?></div>
              <?php endif; ?>
              <a class="btn-cta" href="<?php echo esc_url($cta_url); ?>" target="_blank" rel="noopener">
                <?php echo esc_html($cta_txt); ?>
              </a>
            </div>
          </article>
        <?php endwhile; wp_reset_postdata(); ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
new KL_Palvelut();

/* === Стили карточек (встраиваем прямо сюда для удобства) === */
add_action('wp_head', function(){ ?>
<style>
.service-card{display:flex;flex-direction:column;height:100%;}
.service-excerpt{flex:1 1 auto;margin:0 14px 0;color:#6b7280;}
.service-foot{margin:12px 14px 16px;display:flex;flex-direction:column;align-items:center;gap:10px;}
.service-foot .price-value{font-size:1.6rem;font-weight:800;color:var(--brand,#2c5f2d);line-height:1;}
.btn-cta{display:inline-block;background:#f3c04d;color:#2b2b2b;border-radius:999px;padding:12px 20px;font-weight:700;text-transform:uppercase;letter-spacing:0.02em;text-decoration:none;}
.btn-cta:hover{filter:brightness(0.95);}
</style>
<?php });