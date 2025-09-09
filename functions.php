<?php //子テーマ用関数
if (!defined('ABSPATH')) exit;

//子テーマ用のビジュアルエディタースタイルを適用
add_editor_style();

//以下に子テーマ用の関数を書く
add_filter( 'wp_mail_from', function( $email ) {
    return 'noreply@recruitment.kodomo-plus.co.jp';
});
add_filter( 'wp_mail_from_name', function( $name ) {
    return 'こどもプラス求人サイト';
});

// 会員登録画面からユーザー名を取り除く
add_filter( 'wpmem_register_form_rows', function( $rows ) {
    unset( $rows['username'] );
    return $rows;
});
// メールアドレスからユーザー名を作成する
add_filter( 'wpmem_pre_validate_form', function( $fields ) {
    $fields['username'] = $fields['user_email'];
    return $fields;
});

//会員登録時に（登録者へ）送信されるメールを停止する
add_filter( 'wp_new_user_notification_email', '__return_false' );

// WP-Members関連のエラーを抑制する関数
function suppress_wpmembers_errors() {
    // エラーハンドラー関数を定義
    function custom_error_handler($errno, $errstr, $errfile) {
        // WP-Membersプラグインのエラーを抑制
        if (strpos($errfile, 'wp-members') !== false || 
            strpos($errfile, 'email-as-username-for-wp-members') !== false) {
            // 特定のエラーメッセージのみを抑制
            if (strpos($errstr, 'Undefined array key') !== false) {
                return true; // エラーを抑制
            }
        }
        // その他のエラーは通常通り処理
        return false;
    }
    
    // エラーハンドラーを設定（警告と通知のみ）
    set_error_handler('custom_error_handler', E_WARNING | E_NOTICE);
}

// フロントエンド表示時のみ実行
if (!is_admin() && !defined('DOING_AJAX')) {
    add_action('init', 'suppress_wpmembers_errors', 1);
}


// タクソノミーの子ターム取得用Ajaxハンドラー
function get_taxonomy_children_ajax() {
    $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
    
    if (!$parent_id || !$taxonomy) {
        wp_send_json_error('パラメータが不正です');
    }
    
    $terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'parent' => $parent_id,
    ));
    
    if (is_wp_error($terms) || empty($terms)) {
        wp_send_json_error('子タームが見つかりませんでした');
    }
    
    $result = array();
    foreach ($terms as $term) {
        $result[] = array(
            'term_id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        );
    }
    
    wp_send_json_success($result);
}
add_action('wp_ajax_get_taxonomy_children', 'get_taxonomy_children_ajax');
add_action('wp_ajax_nopriv_get_taxonomy_children', 'get_taxonomy_children_ajax');

// タームリンク取得用Ajaxハンドラー
function get_term_link_ajax() {
    $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
    
    if (!$term_id || !$taxonomy) {
        wp_send_json_error('パラメータが不正です');
    }
    
    $term = get_term($term_id, $taxonomy);
    
    if (is_wp_error($term) || empty($term)) {
        wp_send_json_error('タームが見つかりませんでした');
    }
    
    $link = get_term_link($term);
    
    if (is_wp_error($link)) {
        wp_send_json_error('リンクの取得に失敗しました');
    }
    
    wp_send_json_success($link);
}
add_action('wp_ajax_get_term_link', 'get_term_link_ajax');
add_action('wp_ajax_nopriv_get_term_link', 'get_term_link_ajax');

// スラッグからタームリンク取得用Ajaxハンドラー
function get_term_link_by_slug_ajax() {
    $slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
    
    if (!$slug || !$taxonomy) {
        wp_send_json_error('パラメータが不正です');
    }
    
    $term = get_term_by('slug', $slug, $taxonomy);
    
    if (!$term || is_wp_error($term)) {
        wp_send_json_error('タームが見つかりませんでした');
    }
    
    $link = get_term_link($term);
    
    if (is_wp_error($link)) {
        wp_send_json_error('リンクの取得に失敗しました');
    }
    
    wp_send_json_success($link);
}
add_action('wp_ajax_get_term_link_by_slug', 'get_term_link_by_slug_ajax');
add_action('wp_ajax_nopriv_get_term_link_by_slug', 'get_term_link_by_slug_ajax');


/* ------------------------------------------------------------------------------ 
	親カテゴリー・親タームを選択できないようにする
------------------------------------------------------------------------------ */
require_once(ABSPATH . '/wp-admin/includes/template.php');
class Nocheck_Category_Checklist extends Walker_Category_Checklist {

  function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
    extract($args);
    if ( empty( $taxonomy ) )
      $taxonomy = 'category';

    if ( $taxonomy == 'category' )
      $name = 'post_category';
    else
      $name = 'tax_input['.$taxonomy.']';

    $class = in_array( $category->term_id, $popular_cats ) ? ' class="popular-category"' : '';
    $cat_child = get_term_children( $category->term_id, $taxonomy );

    if( !empty( $cat_child ) ) {
      $output .= "\n<li id='{$taxonomy}-{$category->term_id}'$class>" . '<label class="selectit"><input value="' . $category->slug . '" type="checkbox" name="'.$name.'[]" id="in-'.$taxonomy.'-' . $category->term_id . '"' . checked( in_array( $category->slug, $selected_cats ), true, false ) . disabled( empty( $args['disabled'] ), true, false ) . ' /> ' . esc_html( apply_filters('the_category', $category->name )) . '</label>';
    } else {
      $output .= "\n<li id='{$taxonomy}-{$category->term_id}'$class>" . '<label class="selectit"><input value="' . $category->slug . '" type="checkbox" name="'.$name.'[]" id="in-'.$taxonomy.'-' . $category->term_id . '"' . checked( in_array( $category->slug, $selected_cats ), true, false ) . disabled( empty( $args['disabled'] ), false, false ) . ' /> ' . esc_html( apply_filters('the_category', $category->name )) . '</label>';
    }
  }

}

/**
 * 求人検索のパスURLを処理するための関数
 */

/**
 * カスタムリライトルールを追加
 */
function job_search_rewrite_rules() {
    // 特徴のみのクエリパラメータ対応
    add_rewrite_rule(
        'jobs/features/?$',
        'index.php?post_type=job&job_features_only=1',
        'top'
    );
    
    // /jobs/location/tokyo/ のようなURLルール
    add_rewrite_rule(
        'jobs/location/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]',
        'top'
    );
    
    // /jobs/position/nurse/ のようなURLルール
    add_rewrite_rule(
        'jobs/position/([^/]+)/?$',
        'index.php?post_type=job&job_position=$matches[1]',
        'top'
    );
    
    // /jobs/type/full-time/ のようなURLルール
    add_rewrite_rule(
        'jobs/type/([^/]+)/?$',
        'index.php?post_type=job&job_type=$matches[1]',
        'top'
    );
    
    // /jobs/facility/hospital/ のようなURLルール
    add_rewrite_rule(
        'jobs/facility/([^/]+)/?$',
        'index.php?post_type=job&facility_type=$matches[1]',
        'top'
    );
    
    // /jobs/feature/high-salary/ のようなURLルール
    add_rewrite_rule(
        'jobs/feature/([^/]+)/?$',
        'index.php?post_type=job&job_feature=$matches[1]',
        'top'
    );
    
    // 複合条件のURLルール
    
    // エリア + 職種
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]',
        'top'
    );
    
    // エリア + 雇用形態
    add_rewrite_rule(
        'jobs/location/([^/]+)/type/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_type=$matches[2]',
        'top'
    );
    
    // エリア + 施設形態
    add_rewrite_rule(
        'jobs/location/([^/]+)/facility/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&facility_type=$matches[2]',
        'top'
    );
    
    // エリア + 特徴
    add_rewrite_rule(
        'jobs/location/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_feature=$matches[2]',
        'top'
    );
    
    // 職種 + 雇用形態
    add_rewrite_rule(
        'jobs/position/([^/]+)/type/([^/]+)/?$',
        'index.php?post_type=job&job_position=$matches[1]&job_type=$matches[2]',
        'top'
    );
    
    // 職種 + 施設形態
    add_rewrite_rule(
        'jobs/position/([^/]+)/facility/([^/]+)/?$',
        'index.php?post_type=job&job_position=$matches[1]&facility_type=$matches[2]',
        'top'
    );
    
    // 職種 + 特徴
    add_rewrite_rule(
        'jobs/position/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_position=$matches[1]&job_feature=$matches[2]',
        'top'
    );
    
    // 三つの条件の組み合わせ
    
    // エリア + 職種 + 雇用形態
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/type/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&job_type=$matches[3]',
        'top'
    );
    
    // エリア + 職種 + 施設形態
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/facility/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&facility_type=$matches[3]',
        'top'
    );
    
    // エリア + 職種 + 特徴
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&job_feature=$matches[3]',
        'top'
    );
    
    // 追加: 四つの条件の組み合わせ
    
    // エリア + 職種 + 雇用形態 + 施設形態
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/type/([^/]+)/facility/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&job_type=$matches[3]&facility_type=$matches[4]',
        'top'
    );
    
    // エリア + 職種 + 雇用形態 + 特徴
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/type/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&job_type=$matches[3]&job_feature=$matches[4]',
        'top'
    );
    
    // エリア + 職種 + 施設形態 + 特徴
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/facility/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&facility_type=$matches[3]&job_feature=$matches[4]',
        'top'
    );
    
    // エリア + 雇用形態 + 施設形態 + 特徴
    add_rewrite_rule(
        'jobs/location/([^/]+)/type/([^/]+)/facility/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_type=$matches[2]&facility_type=$matches[3]&job_feature=$matches[4]',
        'top'
    );
    
    // 職種 + 雇用形態 + 施設形態 + 特徴
    add_rewrite_rule(
        'jobs/position/([^/]+)/type/([^/]+)/facility/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_position=$matches[1]&job_type=$matches[2]&facility_type=$matches[3]&job_feature=$matches[4]',
        'top'
    );
    
    // 追加: 五つの条件の組み合わせ（全条件）
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/type/([^/]+)/facility/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&job_type=$matches[3]&facility_type=$matches[4]&job_feature=$matches[5]',
        'top'
    );
    
    // ページネーション対応（例：エリア + 職種の場合）
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/page/([0-9]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&paged=$matches[3]',
        'top'
    );
    
    // 他のページネーションパターンも必要に応じて追加
}
add_action('init', 'job_search_rewrite_rules');

/**
 * クエリ変数を追加
 */
function job_search_query_vars($vars) {
    $vars[] = 'job_location';
    $vars[] = 'job_position';
    $vars[] = 'job_type';
    $vars[] = 'facility_type';
    $vars[] = 'job_feature';
    $vars[] = 'job_features_only'; // 追加: 特徴のみの検索フラグ
    return $vars;
}
add_filter('query_vars', 'job_search_query_vars');

/**
 * URLパスとクエリパラメータを解析してフィルター条件を取得する関数
 */
function get_job_filters_from_url() {
    $filters = array();
    
    // 特徴のみのフラグをチェック
    $features_only = get_query_var('job_features_only');
    if (!empty($features_only)) {
        $filters['features_only'] = true;
    }
    
    // パス型URLからの条件取得
    $location = get_query_var('job_location');
    if (!empty($location)) {
        $filters['location'] = $location;
    }
    
    $position = get_query_var('job_position');
    if (!empty($position)) {
        $filters['position'] = $position;
    }
    
    $job_type = get_query_var('job_type');
    if (!empty($job_type)) {
        $filters['type'] = $job_type;
    }
    
    $facility_type = get_query_var('facility_type');
    if (!empty($facility_type)) {
        $filters['facility'] = $facility_type;
    }
    
    // 単一の特徴（パス型URL用）
    $job_feature = get_query_var('job_feature');
    if (!empty($job_feature)) {
        $filters['feature'] = $job_feature;
    }
    
    // クエリパラメータからの複数特徴取得
    if (isset($_GET['features']) && is_array($_GET['features'])) {
        $filters['features'] = array_map('sanitize_text_field', $_GET['features']);
    }
    
    return $filters;
}

/**
 * 特定の特徴フィルターのみを削除した場合のURLを生成する関数
 */
function remove_feature_from_url($feature_to_remove) {
    // 現在のクエリ変数を取得
    $location_slug = get_query_var('job_location');
    $position_slug = get_query_var('job_position');
    $job_type_slug = get_query_var('job_type');
    $facility_type_slug = get_query_var('facility_type');
    $job_feature_slug = get_query_var('job_feature');
    
    // URLクエリパラメータから特徴の配列を取得（複数選択の場合）
    $feature_slugs = isset($_GET['features']) ? (array)$_GET['features'] : array();
    
    // 特徴のスラッグが単一で指定されている場合、それも追加
    if (!empty($job_feature_slug) && !in_array($job_feature_slug, $feature_slugs)) {
        $feature_slugs[] = $job_feature_slug;
    }
    
    // 削除する特徴を配列から除外
    if (!empty($feature_slugs)) {
        $feature_slugs = array_values(array_diff($feature_slugs, array($feature_to_remove)));
    }
    
    // 単一特徴のパラメータが一致する場合、それも削除
    if ($job_feature_slug === $feature_to_remove) {
        $job_feature_slug = '';
    }
    
    // 残りのフィルターでURLを構築
    $url_parts = array();
    $query_params = array();
    
    if (!empty($location_slug)) {
        $url_parts[] = 'location/' . $location_slug;
    }
    
    if (!empty($position_slug)) {
        $url_parts[] = 'position/' . $position_slug;
    }
    
    if (!empty($job_type_slug)) {
        $url_parts[] = 'type/' . $job_type_slug;
    }
    
    if (!empty($facility_type_slug)) {
        $url_parts[] = 'facility/' . $facility_type_slug;
    }
    
    if (!empty($job_feature_slug)) {
        $url_parts[] = 'feature/' . $job_feature_slug;
    }
    
    // URLの構築
    $base_url = home_url('/jobs/');
    
    if (!empty($url_parts)) {
        $path = implode('/', $url_parts);
        $base_url .= $path . '/';
    } else if (!empty($feature_slugs)) {
        // 他の条件がなく特徴のみが残っている場合は特徴専用エンドポイントを使う
        $base_url .= 'features/';
    } else {
        // すべての条件が削除された場合は求人一覧ページに戻る
        return home_url('/jobs/');
    }
    
    // 複数特徴はクエリパラメータとして追加
    if (!empty($feature_slugs)) {
        foreach ($feature_slugs as $feature) {
            $query_params[] = 'features[]=' . urlencode($feature);
        }
    }
    
    // クエリパラメータの追加
    if (!empty($query_params)) {
        $base_url .= '?' . implode('&', $query_params);
    }
    
    return $base_url;
}

/**
 * 特定のフィルターを削除した場合のURLを生成する関数
 */
function remove_filter_from_url($filter_to_remove) {
    // 現在のクエリ変数を取得
    $location_slug = get_query_var('job_location');
    $position_slug = get_query_var('job_position');
    $job_type_slug = get_query_var('job_type');
    $facility_type_slug = get_query_var('facility_type');
    $job_feature_slug = get_query_var('job_feature');
    
    // URLクエリパラメータから特徴の配列を取得（複数選択の場合）
    $feature_slugs = isset($_GET['features']) ? (array)$_GET['features'] : array();
    
    // 特徴のスラッグが単一で指定されている場合、それも追加
    if (!empty($job_feature_slug) && !in_array($job_feature_slug, $feature_slugs)) {
        $feature_slugs[] = $job_feature_slug;
    }
    
    // 削除するフィルターを処理 - 指定されたフィルターのみを空にする
    switch ($filter_to_remove) {
        case 'location':
            $location_slug = '';
            break;
        case 'position':
            $position_slug = '';
            break;
        case 'type':
            $job_type_slug = '';
            break;
        case 'facility':
            $facility_type_slug = '';
            break;
        case 'feature':
            // 特徴フィルターのみを削除
            $job_feature_slug = '';
            $feature_slugs = array();
            break;
    }
    
    // 残りのフィルターでURLを構築
    $url_parts = array();
    $query_params = array();
    
    // 各フィルターが空でなければURLパーツに追加
    if (!empty($location_slug)) {
        $url_parts[] = 'location/' . $location_slug;
    }
    
    if (!empty($position_slug)) {
        $url_parts[] = 'position/' . $position_slug;
    }
    
    if (!empty($job_type_slug)) {
        $url_parts[] = 'type/' . $job_type_slug;
    }
    
    if (!empty($facility_type_slug)) {
        $url_parts[] = 'facility/' . $facility_type_slug;
    }
    
    if (!empty($job_feature_slug)) {
        $url_parts[] = 'feature/' . $job_feature_slug;
    }
    
    // URLの構築
    $base_url = home_url('/jobs/');
    
    // パスがある場合はそれを追加
    if (!empty($url_parts)) {
        $path = implode('/', $url_parts);
        $base_url .= $path . '/';
    } else if (!empty($feature_slugs)) {
        // 他の条件がなく特徴のみが残っている場合は特徴専用エンドポイントを使う
        $base_url .= 'features/';
    } else {
        // すべての条件が削除された場合は求人一覧ページに戻る
        return home_url('/jobs/');
    }
    
    // 複数特徴はクエリパラメータとして追加
    if (!empty($feature_slugs) && $filter_to_remove !== 'feature') {
        foreach ($feature_slugs as $feature) {
            $query_params[] = 'features[]=' . urlencode($feature);
        }
    }
    
    // クエリパラメータの追加
    if (!empty($query_params)) {
        $base_url .= '?' . implode('&', $query_params);
    }
    
    return $base_url;
}

/**
 * 求人アーカイブページのメインクエリを変更する
 */
function modify_job_archive_query($query) {
    // メインクエリのみに適用
    if (!is_admin() && $query->is_main_query() && 
        (is_post_type_archive('job') || 
        is_tax('job_location') || 
        is_tax('job_position') || 
        is_tax('job_type') || 
        is_tax('facility_type') || 
        is_tax('job_feature'))) {
        
        // URLクエリパラメータから特徴の配列を取得（複数選択の場合）
        $feature_slugs = isset($_GET['features']) && is_array($_GET['features']) ? $_GET['features'] : array();
        
        // 特徴（job_feature）のパラメータがある場合のみ処理
        if (!empty($feature_slugs)) {
            // 既存のtax_queryを取得（なければ新規作成）
            $tax_query = $query->get('tax_query');
            
            if (!is_array($tax_query)) {
                $tax_query = array();
            }
            
            // 特徴の条件を追加
            $tax_query[] = array(
                'taxonomy' => 'job_feature',
                'field'    => 'slug',
                'terms'    => $feature_slugs,
                'operator' => 'IN',
            );
            
            // 複数の条件がある場合はAND条件で結合
            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }
            
            // 更新したtax_queryを設定
            $query->set('tax_query', $tax_query);
        }
        
        // 特徴のみのフラグがある場合（/jobs/features/ エンドポイント）
        if (get_query_var('job_features_only')) {
            // この場合、クエリパラメータの特徴のみでフィルタリング
            if (!empty($feature_slugs)) {
                $tax_query = array(
                    array(
                        'taxonomy' => 'job_feature',
                        'field'    => 'slug',
                        'terms'    => $feature_slugs,
                        'operator' => 'IN',
                    )
                );
                
                $query->set('tax_query', $tax_query);
            }
        }
    }
}
add_action('pre_get_posts', 'modify_job_archive_query');

/**
 * タクソノミーの子ターム取得用AJAX処理 (Nonce検証追加版)
 */
function get_taxonomy_children_callback() {
    // Nonce 検証
    check_ajax_referer('get_taxonomy_children', '_wpnonce'); // JavaScript側とアクション名を合わせる

    $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
    
    if (!$parent_id || !$taxonomy) {
        wp_send_json_error(array('message' => 'パラメータが不正です (parent_id or taxonomy missing)'));
        wp_die();
    }
    
    $terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'parent' => $parent_id,
    ));
    
    if (is_wp_error($terms)) {
        wp_send_json_error(array('message' => 'タームの取得に失敗しました: ' . $terms->get_error_message()));
        wp_die();
    }
    
    if (empty($terms)) {
        wp_send_json_success(array()); // 子タームがない場合は空の成功レスポンスを返す
        wp_die();
    }
    
    $result = array();
    foreach ($terms as $term) {
        $result[] = array(
            'term_id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        );
    }
    
    wp_send_json_success($result);
    wp_die(); // 忘れずに
}

/**
 * タームのURLを取得するAJAX処理
 */
function get_term_link_callback() {
    // セキュリティチェック
    if (!isset($_POST['term_id']) || !isset($_POST['taxonomy'])) {
        wp_send_json_error('Invalid request');
    }
    
    $term_id = intval($_POST['term_id']);
    $taxonomy = sanitize_text_field($_POST['taxonomy']);
    
    $term = get_term($term_id, $taxonomy);
    if (is_wp_error($term)) {
        wp_send_json_error($term->get_error_message());
    }
    
    $term_link = get_term_link($term);
    if (is_wp_error($term_link)) {
        wp_send_json_error($term_link->get_error_message());
    }
    
    wp_send_json_success($term_link);
}
add_action('wp_ajax_get_term_link', 'get_term_link_callback');
add_action('wp_ajax_nopriv_get_term_link', 'get_term_link_callback');

/**
 * スラッグからタームリンクを取得するAJAX処理
 */
function get_term_link_by_slug_callback() {
    // セキュリティチェック
    if (!isset($_POST['slug']) || !isset($_POST['taxonomy'])) {
        wp_send_json_error('Invalid request');
    }
    
    $slug = sanitize_text_field($_POST['slug']);
    $taxonomy = sanitize_text_field($_POST['taxonomy']);
    
    $term = get_term_by('slug', $slug, $taxonomy);
    if (!$term || is_wp_error($term)) {
        wp_send_json_error('Term not found');
    }
    
    $term_link = get_term_link($term);
    if (is_wp_error($term_link)) {
        wp_send_json_error($term_link->get_error_message());
    }
    
    wp_send_json_success($term_link);
}
add_action('wp_ajax_get_term_link_by_slug', 'get_term_link_by_slug_callback');
add_action('wp_ajax_nopriv_get_term_link_by_slug', 'get_term_link_by_slug_callback');
/**
 * スラッグからタームIDと名前を取得するAJAX処理
 */
function my_ajax_get_term_id_by_slug_callback() {
    // Nonce 検証
    check_ajax_referer('get_term_id_by_slug_nonce', '_wpnonce'); 

    $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
    $slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';

    if (empty($taxonomy) || empty($slug)) {
        wp_send_json_error(array('message' => 'パラメータが不正です (taxonomy or slug missing)'));
        wp_die();
    }

    $term = get_term_by('slug', $slug, $taxonomy);

    if ($term && !is_wp_error($term)) {
        wp_send_json_success(array('term_id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug));
    } else {
        wp_send_json_error(array('message' => 'タームが見つかりませんでした (slug: ' . $slug . ', taxonomy: ' . $taxonomy . ')'));
    }
    wp_die(); // 忘れずに
}
add_action('wp_ajax_get_term_id_by_slug', 'my_ajax_get_term_id_by_slug_callback');
add_action('wp_ajax_nopriv_get_term_id_by_slug', 'my_ajax_get_term_id_by_slug_callback'); // 必要に応じてnoprivも

/**
 * URLが変更されたときにリライトルールをフラッシュする
 */
function flush_rewrite_rules_on_theme_activation() {
    if (get_option('job_search_rewrite_rules_flushed') != '1') {
        flush_rewrite_rules();
        update_option('job_search_rewrite_rules_flushed', '1');
    }
}
add_action('after_switch_theme', 'flush_rewrite_rules_on_theme_activation');

// リライトルールの強制フラッシュと再登録
function force_rewrite_rules_refresh() {
    // 初回読み込み時にのみ実行
    if (!get_option('force_rewrite_refresh_done')) {
        // リライトルールを追加
        job_search_rewrite_rules();
        
        // リライトルールをフラッシュ
        flush_rewrite_rules();
        
        // 実行済みフラグを設定
        update_option('force_rewrite_refresh_done', '1');
    }
}
add_action('init', 'force_rewrite_rules_refresh', 99);

// 特徴のみのリライトルールを追加した後にフラッシュする
function flush_features_rewrite_rules() {
    if (!get_option('job_features_rewrite_flushed')) {
        flush_rewrite_rules();
        update_option('job_features_rewrite_flushed', true);
    }
}
add_action('init', 'flush_features_rewrite_rules', 999);

// リライトルールのデバッグ（必要に応じて）
function debug_rewrite_rules() {
    if (current_user_can('manage_options') && isset($_GET['debug_rewrite'])) {
        global $wp_rewrite;
        echo '<pre>';
        print_r($wp_rewrite->rules);
        echo '</pre>';
        exit;
    }
}
add_action('init', 'debug_rewrite_rules', 100);

// 以下のコードがfunctions.phpに追加されているか確認してください
function job_path_query_vars($vars) {
    $vars[] = 'job_path';
    return $vars;
}
add_filter('query_vars', 'job_path_query_vars');

// 求人ステータス変更・削除用のアクション処理
add_action('admin_post_draft_job', 'set_job_to_draft');
add_action('admin_post_publish_job', 'set_job_to_publish');
add_action('admin_post_delete_job', 'delete_job_post');

/**
 * 求人ステータス変更・削除用のアクション処理の修正版
 */
function set_job_to_draft() {
    if (!isset($_GET['job_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('無効なリクエストです。');
    }
    
    $job_id = intval($_GET['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_GET['_wpnonce'], 'draft_job_' . $job_id)) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック - 加盟教室ユーザー用に修正
    $job_post = get_post($job_id);
    if (!$job_post || $job_post->post_type !== 'job') {
        wp_die('この求人が見つかりません。');
    }
    
    // agencyユーザーと管理者の両方に権限を与える
    $current_user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    $is_agency = in_array('agency', (array)$current_user->roles);
    
    if ($job_post->post_author != $current_user_id && !current_user_can('administrator')) {
        wp_die('この求人を編集する権限がありません。');
    }
    
    // 下書きに変更
    wp_update_post(array(
        'ID' => $job_id,
        'post_status' => 'draft'
    ));
    
    // リダイレクト
    wp_redirect(home_url('/job-list/?status=drafted'));
    exit;
}

/**
 * 求人を公開に変更 - 修正版
 */
function set_job_to_publish() {
    if (!isset($_GET['job_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('無効なリクエストです。');
    }
    
    $job_id = intval($_GET['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_GET['_wpnonce'], 'publish_job_' . $job_id)) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック - 加盟教室ユーザー用に修正
    $job_post = get_post($job_id);
    if (!$job_post || $job_post->post_type !== 'job') {
        wp_die('この求人が見つかりません。');
    }
    
    // agencyユーザーと管理者の両方に権限を与える
    $current_user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    $is_agency = in_array('agency', (array)$current_user->roles);
    
    if ($job_post->post_author != $current_user_id && !current_user_can('administrator')) {
        wp_die('この求人を編集する権限がありません。');
    }
    
    // 公開に変更
    wp_update_post(array(
        'ID' => $job_id,
        'post_status' => 'publish'
    ));
    
    // リダイレクト
    wp_redirect(home_url('/job-list/?status=published'));
    exit;
}

/**
 * 求人を削除 - 修正版
 */
function delete_job_post() {
    if (!isset($_GET['job_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('無効なリクエストです。');
    }
    
    $job_id = intval($_GET['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_job_' . $job_id)) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック - 加盟教室ユーザー用に修正
    $job_post = get_post($job_id);
    if (!$job_post || $job_post->post_type !== 'job') {
        wp_die('この求人が見つかりません。');
    }
    
    // agencyユーザーと管理者の両方に権限を与える
    $current_user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    $is_agency = in_array('agency', (array)$current_user->roles);
    
    if ($job_post->post_author != $current_user_id && !current_user_can('administrator')) {
        wp_die('この求人を削除する権限がありません。');
    }
    
    // 削除
    wp_trash_post($job_id);
    
    // リダイレクト
    wp_redirect(home_url('/job-list/?status=deleted'));
    exit;
}
/**
 * 加盟教室(agency)ロールに必要な権限を追加
 */
function add_capabilities_to_agency_role() {
    // agency ロールを取得
    $role = get_role('agency');
    
    if ($role) {
        // 編集・削除関連の権限を追加
        $role->add_cap('edit_posts', true);
        $role->add_cap('delete_posts', true);
        $role->add_cap('publish_posts', true);
        $role->add_cap('edit_published_posts', true);
        $role->add_cap('delete_published_posts', true);
        
        // job カスタム投稿タイプ用の権限
        $role->add_cap('edit_job', true);
        $role->add_cap('read_job', true);
        $role->add_cap('delete_job', true);
        $role->add_cap('edit_jobs', true);
        $role->add_cap('edit_others_jobs', false); // 他のユーザーの投稿は編集不可
        $role->add_cap('publish_jobs', true);
        $role->add_cap('read_private_jobs', false); // プライベート投稿は読み取り不可
        $role->add_cap('edit_published_jobs', true);
        $role->add_cap('delete_published_jobs', true);
    }
}
add_action('init', 'add_capabilities_to_agency_role', 10);
/**
 * 求人用カスタムフィールドとメタボックスの設定
 */

/**
 * 求人投稿のメタボックスを追加
 */
function add_job_meta_boxes() {
    add_meta_box(
        'job_details',
        '求人詳細情報',
        'render_job_details_meta_box',
        'job',
        'normal',
        'high'
    );
    
    add_meta_box(
        'facility_details',
        '施設情報',
        'render_facility_details_meta_box',
        'job',
        'normal',
        'high'
    );
    
    add_meta_box(
        'workplace_environment',
        '職場環境',
        'render_workplace_environment_meta_box',
        'job',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_job_meta_boxes');

/**
 * 求人詳細情報のメタボックスをレンダリング
 */
function render_job_details_meta_box($post) {
    // nonce フィールドを作成
    wp_nonce_field('save_job_details', 'job_details_nonce');
    
    // 現在のカスタムフィールド値を取得
    $salary_range = get_post_meta($post->ID, 'salary_range', true);
    $working_hours = get_post_meta($post->ID, 'working_hours', true);
    $holidays = get_post_meta($post->ID, 'holidays', true);
    $benefits = get_post_meta($post->ID, 'benefits', true);
    $requirements = get_post_meta($post->ID, 'requirements', true);
    $application_process = get_post_meta($post->ID, 'application_process', true);
    $contact_info = get_post_meta($post->ID, 'contact_info', true);
    $bonus_raise = get_post_meta($post->ID, 'bonus_raise', true);
    
    // フォームを表示
    ?>
    <style>
        .job-form-row {
            margin-bottom: 15px;
        }
        .job-form-row label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .job-form-row input[type="text"],
        .job-form-row textarea {
            width: 100%;
        }
        .required {
            color: #f00;
        }
    </style>
    
    <div class="job-form-row">
        <label for="salary_range">給与範囲 <span class="required">*</span></label>
        <input type="text" id="salary_range" name="salary_range" value="<?php echo esc_attr($salary_range); ?>" required>
        <p class="description">例: 月給180,000円〜250,000円</p>
    </div>
    
    <div class="job-form-row">
    <label for="working_hours">勤務時間 <span class="required">*</span></label>
    <textarea id="working_hours" name="working_hours" rows="3" required><?php echo esc_textarea($working_hours); ?></textarea>
    <p class="description">例: 9:00〜18:00（休憩60分）<br>複数の勤務時間がある場合は改行してください</p>
</div>
    
    <div class="job-form-row">
    <label for="holidays">休日・休暇 <span class="required">*</span></label>
    <textarea id="holidays" name="holidays" rows="3" required><?php echo esc_textarea($holidays); ?></textarea>
    <p class="description">例: 土日祝、年末年始、有給休暇あり<br>複数の制度がある場合は改行してください</p>
</div>
    
    <div class="job-form-row">
        <label for="benefits">福利厚生</label>
        <textarea id="benefits" name="benefits" rows="4"><?php echo esc_textarea($benefits); ?></textarea>
        <p class="description">社会保険、交通費支給、各種手当など</p>
    </div>
    
    <div class="job-form-row">
        <label for="bonus_raise">昇給・賞与</label>
        <textarea id="bonus_raise" name="bonus_raise" rows="4"><?php echo esc_textarea($bonus_raise); ?></textarea>
        <p class="description">昇給制度や賞与の詳細など</p>
    </div>
    
    <div class="job-form-row">
        <label for="requirements">応募要件</label>
        <textarea id="requirements" name="requirements" rows="4"><?php echo esc_textarea($requirements); ?></textarea>
        <p class="description">必要な資格や経験など</p>
    </div>
    <div class="job-form-row">
        <label for="contact_info">仕事内容 <span class="required">*</span></label>
        <textarea id="contact_info" name="contact_info" rows="4" required><?php echo esc_textarea($contact_info); ?></textarea>
        <p class="description">電話番号、メールアドレス、応募フォームURLなど</p>
    </div>
    <div class="job-form-row">
        <label for="application_process">選考プロセス</label>
        <textarea id="application_process" name="application_process" rows="4"><?php echo esc_textarea($application_process); ?></textarea>
        <p class="description">書類選考、面接回数など</p>
    </div>
    
    <?php
}

/**
 * 施設情報のメタボックスをレンダリング
 */
function render_facility_details_meta_box($post) {
    // nonce フィールドを作成
    wp_nonce_field('save_facility_details', 'facility_details_nonce');
    
    // 現在のカスタムフィールド値を取得
    $facility_name = get_post_meta($post->ID, 'facility_name', true);
    $facility_address = get_post_meta($post->ID, 'facility_address', true);
    $facility_tel = get_post_meta($post->ID, 'facility_tel', true);
    $facility_hours = get_post_meta($post->ID, 'facility_hours', true);
    $facility_url = get_post_meta($post->ID, 'facility_url', true);
    $facility_company = get_post_meta($post->ID, 'facility_company', true);
    $capacity = get_post_meta($post->ID, 'capacity', true);
    $staff_composition = get_post_meta($post->ID, 'staff_composition', true);
    $company_url = get_post_meta($post->ID, 'company_url', true);
    $capacity = get_post_meta($post->ID, 'capacity', true);
    $staff_composition = get_post_meta($post->ID, 'staff_composition', true);
    // フォームを表示
    ?>
    <div class="job-form-row">
        <label for="facility_name">施設名 <span class="required">*</span></label>
        <input type="text" id="facility_name" name="facility_name" value="<?php echo esc_attr($facility_name); ?>" required>
    </div>
    
    <div class="job-form-row">
        <label for="facility_company">運営会社名</label>
        <input type="text" id="facility_company" name="facility_company" value="<?php echo esc_attr($facility_company); ?>">
    </div>
    <div class="job-form-row">
        <label for="facility_company">運営会社のWebサイトURL</label>
        <input type="text" id="company_url" name="company_url" value="<?php echo esc_attr($company_url); ?>">
    </div>
    <div class="job-form-row">
        <label for="facility_address">施設住所 <span class="required">*</span></label>
        <input type="text" id="facility_address" name="facility_address" value="<?php echo esc_attr($facility_address); ?>" required>
        <p class="description">例: 〒123-4567 神奈川県横浜市○○区△△町1-2-3</p>
    </div>
    
    <div class="job-form-row">
        <label for="capacity">利用者定員数</label>
        <input type="text" id="capacity" name="capacity" value="<?php echo esc_attr($capacity); ?>">
        <p class="description">例: 60名（0〜5歳児）</p>
    </div>
    
    <div class="job-form-row">
        <label for="staff_composition">スタッフ構成</label>
        <textarea id="staff_composition" name="staff_composition" rows="4"><?php echo esc_textarea($staff_composition); ?></textarea>
        <p class="description">例: 園長1名、主任保育士2名、保育士12名、栄養士2名、調理員3名、事務員1名</p>
    </div>
    
    <div class="job-form-row">
        <label for="facility_tel">施設電話番号</label>
        <input type="text" id="facility_tel" name="facility_tel" value="<?php echo esc_attr($facility_tel); ?>">
    </div>
    
    <div class="job-form-row">
        <label for="facility_hours">施設営業時間</label>
        <input type="text" id="facility_hours" name="facility_hours" value="<?php echo esc_attr($facility_hours); ?>">
    </div>
    
    <div class="job-form-row">
        <label for="facility_url">施設WebサイトURL</label>
        <input type="url" id="facility_url" name="facility_url" value="<?php echo esc_url($facility_url); ?>">
    </div>
    <?php
}

/**
 * 職場環境のメタボックスをレンダリング - 更新版
 */
function render_workplace_environment_meta_box($post) {
    // nonce フィールドを作成
    wp_nonce_field('save_workplace_environment', 'workplace_environment_nonce');
    
    // 既存のデータを取得
    $daily_schedule = get_post_meta($post->ID, 'daily_schedule', true);
    $staff_voices = get_post_meta($post->ID, 'staff_voices', true);
    
    // 新形式のデータ
    $daily_schedule_items = get_post_meta($post->ID, 'daily_schedule_items', true);
    $staff_voice_items = get_post_meta($post->ID, 'staff_voice_items', true);
    
    // JavaScript とスタイルを追加
    ?>
    <style>
    .schedule-items, .voice-items {
        margin-bottom: 15px;
    }
    .schedule-item, .voice-item {
        border: 1px solid #ddd;
        padding: 10px;
        margin-bottom: 10px;
        background: #f9f9f9;
        position: relative;
    }
    .schedule-row, .voice-row {
        margin-bottom: 10px;
    }
    .remove-btn {
        position: absolute;
        top: 5px;
        right: 5px;
        color: red;
        cursor: pointer;
    }
    .image-preview {
        max-width: 100px;
        max-height: 100px;
        margin-top: 5px;
    }
    </style>
    
    <!-- 旧フォーマットのフィールド（バックアップとして保持） -->
    <div style="display: none;">
        <div class="job-form-row">
            <label for="daily_schedule">一日の流れ（旧形式）</label>
            <textarea id="daily_schedule" name="daily_schedule" rows="8"><?php echo esc_textarea($daily_schedule); ?></textarea>
        </div>
        
        <div class="job-form-row">
            <label for="staff_voices">職員の声（旧形式）</label>
            <textarea id="staff_voices" name="staff_voices" rows="8"><?php echo esc_textarea($staff_voices); ?></textarea>
        </div>
    </div>
    
    <!-- 新フォーマットの一日の流れ -->
    <div class="workplace-section">
        <h4>仕事の一日の流れ</h4>
        <div id="schedule-container" class="schedule-items">
            <?php
            if (is_array($daily_schedule_items) && !empty($daily_schedule_items)) {
                foreach ($daily_schedule_items as $index => $item) {
                    ?>
                    <div class="schedule-item">
                        <span class="remove-btn" onclick="removeScheduleItem(this)">✕</span>
                        <div class="schedule-row">
                            <label>時間:</label>
                            <input type="text" name="daily_schedule_time[]" value="<?php echo esc_attr($item['time']); ?>" placeholder="9:00" style="width: 100px;">
                        </div>
                        <div class="schedule-row">
                            <label>タイトル:</label>
                            <input type="text" name="daily_schedule_title[]" value="<?php echo esc_attr($item['title']); ?>" placeholder="出社・朝礼" style="width: 250px;">
                        </div>
                        <div class="schedule-row">
                            <label>詳細:</label>
                            <textarea name="daily_schedule_description[]" rows="3" style="width: 100%;"><?php echo esc_textarea($item['description']); ?></textarea>
                        </div>
                    </div>
                    <?php
                }
            } else {
                // 空のテンプレート
                ?>
                <div class="schedule-item">
                    <span class="remove-btn" onclick="removeScheduleItem(this)">✕</span>
                    <div class="schedule-row">
                        <label>時間:</label>
                        <input type="text" name="daily_schedule_time[]" placeholder="9:00" style="width: 100px;">
                    </div>
                    <div class="schedule-row">
                        <label>タイトル:</label>
                        <input type="text" name="daily_schedule_title[]" placeholder="出社・朝礼" style="width: 250px;">
                    </div>
                    <div class="schedule-row">
                        <label>詳細:</label>
                        <textarea name="daily_schedule_description[]" rows="3" style="width: 100%;"></textarea>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
        <button type="button" class="button" onclick="addScheduleItem()">時間枠を追加</button>
    </div>
    
    <!-- 新フォーマットの職員の声 -->
    <div class="workplace-section" style="margin-top: 20px;">
        <h4>職員の声</h4>
        <div id="voice-container" class="voice-items">
            <?php
            if (is_array($staff_voice_items) && !empty($staff_voice_items)) {
                foreach ($staff_voice_items as $index => $item) {
                    $image_url = '';
                    if (!empty($item['image_id'])) {
                        $image_url = wp_get_attachment_url($item['image_id']);
                    }
                    ?>
                    <div class="voice-item">
                        <span class="remove-btn" onclick="removeVoiceItem(this)">✕</span>
                        <div class="voice-row">
                            <label>サムネイル:</label>
                            <input type="hidden" name="staff_voice_image[]" value="<?php echo esc_attr($item['image_id']); ?>" class="voice-image-id">
                            <button type="button" class="button upload-image" onclick="uploadVoiceImage(this)">画像を選択</button>
                            <button type="button" class="button remove-image" onclick="removeVoiceImage(this)" <?php echo empty($image_url) ? 'style="display:none;"' : ''; ?>>画像を削除</button>
                            <div class="image-preview-container">
                                <?php if (!empty($image_url)): ?>
                                <img src="<?php echo esc_url($image_url); ?>" alt="" class="image-preview">
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="voice-row">
                            <label>職種:</label>
                            <input type="text" name="staff_voice_role[]" value="<?php echo esc_attr($item['role']); ?>" placeholder="保育士" style="width: 250px;">
                        </div>
                        <div class="voice-row">
                            <label>勤続年数:</label>
                            <input type="text" name="staff_voice_years[]" value="<?php echo esc_attr($item['years']); ?>" placeholder="3年目" style="width: 100px;">
                        </div>
                        <div class="voice-row">
                            <label>コメント:</label>
                            <textarea name="staff_voice_comment[]" rows="4" style="width: 100%;"><?php echo esc_textarea($item['comment']); ?></textarea>
                        </div>
                    </div>
                    <?php
                }
            } else {
                // 空のテンプレート
                ?>
                <div class="voice-item">
                    <span class="remove-btn" onclick="removeVoiceItem(this)">✕</span>
                    <div class="voice-row">
                        <label>サムネイル:</label>
                        <input type="hidden" name="staff_voice_image[]" value="" class="voice-image-id">
                        <button type="button" class="button upload-image" onclick="uploadVoiceImage(this)">画像を選択</button>
                        <button type="button" class="button remove-image" onclick="removeVoiceImage(this)" style="display:none;">画像を削除</button>
                        <div class="image-preview-container"></div>
                    </div>
                    <div class="voice-row">
                        <label>職種:</label>
                        <input type="text" name="staff_voice_role[]" placeholder="保育士" style="width: 250px;">
                    </div>
                    <div class="voice-row">
                        <label>勤続年数:</label>
                        <input type="text" name="staff_voice_years[]" placeholder="3年目" style="width: 100px;">
                    </div>
                    <div class="voice-row">
                        <label>コメント:</label>
                        <textarea name="staff_voice_comment[]" rows="4" style="width: 100%;"></textarea>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
        <button type="button" class="button" onclick="addVoiceItem()">職員の声を追加</button>
    </div>
    
    <script>
    // 一日の流れを追加
    function addScheduleItem() {
        var template = document.querySelector('#schedule-container .schedule-item:first-child').cloneNode(true);
        // 入力内容をクリア
        template.querySelectorAll('input, textarea').forEach(function(el) {
            el.value = '';
        });
        document.getElementById('schedule-container').appendChild(template);
    }
    
    // 一日の流れを削除
    function removeScheduleItem(button) {
        var container = document.getElementById('schedule-container');
        if (container.children.length > 1) {
            button.parentNode.remove();
        } else {
            alert('少なくとも1つの項目が必要です');
        }
    }
    
    // 職員の声を追加
    function addVoiceItem() {
        var template = document.querySelector('#voice-container .voice-item:first-child').cloneNode(true);
        // 入力内容をクリア
        template.querySelectorAll('input, textarea').forEach(function(el) {
            el.value = '';
        });
        template.querySelector('.image-preview-container').innerHTML = '';
        template.querySelector('.remove-image').style.display = 'none';
        document.getElementById('voice-container').appendChild(template);
    }
    
    // 職員の声を削除
    function removeVoiceItem(button) {
        var container = document.getElementById('voice-container');
        if (container.children.length > 1) {
            button.parentNode.remove();
        } else {
            alert('少なくとも1つの項目が必要です');
        }
    }
    
    // 職員画像をアップロード
    function uploadVoiceImage(button) {
        var frame = wp.media({
            title: '職員の声の画像を選択',
            button: {
                text: '画像を選択'
            },
            multiple: false
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            var container = button.closest('.voice-item');
            var imageId = container.querySelector('.voice-image-id');
            var previewContainer = container.querySelector('.image-preview-container');
            var removeButton = container.querySelector('.remove-image');
            
            imageId.value = attachment.id;
            previewContainer.innerHTML = '<img src="' + attachment.url + '" alt="" class="image-preview">';
            removeButton.style.display = 'inline-block';
        });
        
        frame.open();
    }
    
    // 職員画像を削除
    function removeVoiceImage(button) {
        var container = button.closest('.voice-item');
        var imageId = container.querySelector('.voice-image-id');
        var previewContainer = container.querySelector('.image-preview-container');
        
        imageId.value = '';
        previewContainer.innerHTML = '';
        button.style.display = 'none';
    }
    </script>
    <?php
}

/**
 * 管理画面と前面の編集ページで一貫したデータ構造を使用するための修正
 */
function save_workplace_environment_data($post_id) {
    // すでにカスタムフィールドを保存する関数が実行されている場合は終了
    if (did_action('save_post_' . get_post_type($post_id)) > 1) {
        return;
    }
    
    // 自動保存の場合は何もしない
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // 権限チェック
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // nonceチェック
    if (!isset($_POST['workplace_environment_nonce']) || 
        !wp_verify_nonce($_POST['workplace_environment_nonce'], 'save_workplace_environment')) {
        return;
    }
    
    // 旧形式のフィールドも保存（互換性のため）
    if (isset($_POST['daily_schedule'])) {
        update_post_meta($post_id, 'daily_schedule', wp_kses_post($_POST['daily_schedule']));
    }
    
    if (isset($_POST['staff_voices'])) {
        update_post_meta($post_id, 'staff_voices', wp_kses_post($_POST['staff_voices']));
    }
    
    // 新形式の一日の流れデータ（配列形式）
    if (isset($_POST['daily_schedule_time']) && is_array($_POST['daily_schedule_time'])) {
        $schedule_items = array();
        $count = count($_POST['daily_schedule_time']);
        
        for ($i = 0; $i < $count; $i++) {
            if (!empty($_POST['daily_schedule_time'][$i])) {
                $schedule_items[] = array(
                    'time' => sanitize_text_field($_POST['daily_schedule_time'][$i]),
                    'title' => sanitize_text_field($_POST['daily_schedule_title'][$i]),
                    'description' => wp_kses_post($_POST['daily_schedule_description'][$i])
                );
            }
        }
        
        update_post_meta($post_id, 'daily_schedule_items', $schedule_items);
    }
    
    // 新形式の職員の声データ（配列形式）
    if (isset($_POST['staff_voice_role']) && is_array($_POST['staff_voice_role'])) {
        $voice_items = array();
        $count = count($_POST['staff_voice_role']);
        
        for ($i = 0; $i < $count; $i++) {
            if (!empty($_POST['staff_voice_role'][$i])) {
                $voice_items[] = array(
                    'image_id' => intval($_POST['staff_voice_image'][$i]),
                    'role' => sanitize_text_field($_POST['staff_voice_role'][$i]),
                    'years' => sanitize_text_field($_POST['staff_voice_years'][$i]),
                    'comment' => wp_kses_post($_POST['staff_voice_comment'][$i])
                );
            }
        }
        
        update_post_meta($post_id, 'staff_voice_items', $voice_items);
    }
}
add_action('save_post_job', 'save_workplace_environment_data', 20);

/**
 * 管理画面メディア関連のスクリプト読み込み
 */
function load_admin_media_scripts($hook) {
    global $post;
    
    // 投稿編集画面のみに読み込み
    if ($hook == 'post.php' || $hook == 'post-new.php') {
        if (isset($post) && $post->post_type == 'job') {
            wp_enqueue_media();
        }
    }
}
add_action('admin_enqueue_scripts', 'load_admin_media_scripts');

/**
 * フロントエンドと管理画面の保存処理を統一するためのデータ同期
 */
function sync_workplace_environment_data($post_id) {
    // 通常の保存処理が完了した後に実行
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // 該当の投稿タイプのみ処理
    if (get_post_type($post_id) !== 'job') {
        return;
    }
    
    // 新形式のデータが存在するか確認
    $daily_schedule_items = get_post_meta($post_id, 'daily_schedule_items', true);
    $staff_voice_items = get_post_meta($post_id, 'staff_voice_items', true);
    
    // 旧形式のデータを取得
    $daily_schedule = get_post_meta($post_id, 'daily_schedule', true);
    $staff_voices = get_post_meta($post_id, 'staff_voices', true);
    
    // 新形式のデータが存在しない場合、旧形式から変換を試みる
    if (empty($daily_schedule_items) && !empty($daily_schedule)) {
        // 簡易的な変換処理（実際のデータ構造によって調整が必要）
        $schedule_items = array(
            array(
                'time' => '9:00',
                'title' => '業務開始',
                'description' => $daily_schedule
            )
        );
        update_post_meta($post_id, 'daily_schedule_items', $schedule_items);
    }
    
    if (empty($staff_voice_items) && !empty($staff_voices)) {
        // 簡易的な変換処理（実際のデータ構造によって調整が必要）
        $voice_items = array(
            array(
                'image_id' => 0,
                'role' => '職員',
                'years' => '勤続期間',
                'comment' => $staff_voices
            )
        );
        update_post_meta($post_id, 'staff_voice_items', $voice_items);
    }
}
add_action('save_post', 'sync_workplace_environment_data', 30);

/**
 * カスタムフィールドのデータを保存
 */
function save_job_meta_data($post_id) {
    // 自動保存の場合は何もしない
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // 権限チェック
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // 求人詳細情報の保存
    if (isset($_POST['job_details_nonce']) && wp_verify_nonce($_POST['job_details_nonce'], 'save_job_details')) {
        if (isset($_POST['salary_range'])) {
            update_post_meta($post_id, 'salary_range', sanitize_text_field($_POST['salary_range']));
        }
        
        if (isset($_POST['working_hours'])) {
            update_post_meta($post_id, 'working_hours', sanitize_text_field($_POST['working_hours']));
        }
        
        if (isset($_POST['holidays'])) {
            update_post_meta($post_id, 'holidays', sanitize_text_field($_POST['holidays']));
        }
        
        if (isset($_POST['benefits'])) {
            update_post_meta($post_id, 'benefits', wp_kses_post($_POST['benefits']));
        }
        
        if (isset($_POST['bonus_raise'])) {
            update_post_meta($post_id, 'bonus_raise', wp_kses_post($_POST['bonus_raise']));
        }
        
        if (isset($_POST['requirements'])) {
            update_post_meta($post_id, 'requirements', wp_kses_post($_POST['requirements']));
        }
        
        if (isset($_POST['application_process'])) {
            update_post_meta($post_id, 'application_process', wp_kses_post($_POST['application_process']));
        }
        
        if (isset($_POST['contact_info'])) {
            update_post_meta($post_id, 'contact_info', wp_kses_post($_POST['contact_info']));
        }
    }
    
    // 施設情報の保存
    if (isset($_POST['facility_details_nonce']) && wp_verify_nonce($_POST['facility_details_nonce'], 'save_facility_details')) {
        if (isset($_POST['facility_name'])) {
            update_post_meta($post_id, 'facility_name', sanitize_text_field($_POST['facility_name']));
        }
        
        if (isset($_POST['facility_company'])) {
            update_post_meta($post_id, 'facility_company', sanitize_text_field($_POST['facility_company']));
        }
        
        if (isset($_POST['facility_address'])) {
            update_post_meta($post_id, 'facility_address', sanitize_text_field($_POST['facility_address']));
        }
        
        if (isset($_POST['capacity'])) {
            update_post_meta($post_id, 'capacity', sanitize_text_field($_POST['capacity']));
        }
        
        if (isset($_POST['staff_composition'])) {
            update_post_meta($post_id, 'staff_composition', wp_kses_post($_POST['staff_composition']));
        }
        
        if (isset($_POST['facility_tel'])) {
            update_post_meta($post_id, 'facility_tel', sanitize_text_field($_POST['facility_tel']));
        }
        
        if (isset($_POST['facility_hours'])) {
            update_post_meta($post_id, 'facility_hours', sanitize_text_field($_POST['facility_hours']));
        }
        
        if (isset($_POST['facility_url'])) {
            update_post_meta($post_id, 'facility_url', esc_url_raw($_POST['facility_url']));
        }
    }
    
    // 職場環境の保存
    if (isset($_POST['workplace_environment_nonce']) && wp_verify_nonce($_POST['workplace_environment_nonce'], 'save_workplace_environment')) {
        if (isset($_POST['daily_schedule'])) {
            update_post_meta($post_id, 'daily_schedule', wp_kses_post($_POST['daily_schedule']));
        }
        
        if (isset($_POST['staff_voices'])) {
            update_post_meta($post_id, 'staff_voices', wp_kses_post($_POST['staff_voices']));
        }
    }
}
add_action('save_post_job', 'save_job_meta_data');

// 追加のカスタムフィールドを設定
function add_additional_job_fields($post_id) {
    // 本文タイトル
    if (isset($_POST['job_content_title'])) {
        update_post_meta($post_id, 'job_content_title', sanitize_text_field($_POST['job_content_title']));
    }
    
    // GoogleMap埋め込みコード
    if (isset($_POST['facility_map'])) {
        update_post_meta($post_id, 'facility_map', wp_kses($_POST['facility_map'], array(
            'iframe' => array(
                'src' => array(),
                'width' => array(),
                'height' => array(),
                'frameborder' => array(),
                'style' => array(),
                'allowfullscreen' => array()
            )
        )));
    }
    
    // 仕事の一日の流れ（配列形式）
    if (isset($_POST['daily_schedule_time']) && is_array($_POST['daily_schedule_time'])) {
        $schedule_items = array();
        $count = count($_POST['daily_schedule_time']);
        
        for ($i = 0; $i < $count; $i++) {
            if (!empty($_POST['daily_schedule_time'][$i])) {
                $schedule_items[] = array(
                    'time' => sanitize_text_field($_POST['daily_schedule_time'][$i]),
                    'title' => sanitize_text_field($_POST['daily_schedule_title'][$i]),
                    'description' => wp_kses_post($_POST['daily_schedule_description'][$i])
                );
            }
        }
        
        update_post_meta($post_id, 'daily_schedule_items', $schedule_items);
    }
    
    // 職員の声（配列形式）
    if (isset($_POST['staff_voice_role']) && is_array($_POST['staff_voice_role'])) {
        $voice_items = array();
        $count = count($_POST['staff_voice_role']);
        
        for ($i = 0; $i < $count; $i++) {
            if (!empty($_POST['staff_voice_role'][$i])) {
                $voice_items[] = array(
                    'image_id' => intval($_POST['staff_voice_image'][$i]),
                    'role' => sanitize_text_field($_POST['staff_voice_role'][$i]),
                    'years' => sanitize_text_field($_POST['staff_voice_years'][$i]),
                    'comment' => wp_kses_post($_POST['staff_voice_comment'][$i])
                );
            }
        }
        
        update_post_meta($post_id, 'staff_voice_items', $voice_items);
    }
}

// 求人投稿保存時にカスタムフィールドを処理
add_action('save_post_job', function($post_id) {
    // 自動保存の場合は何もしない
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // 権限チェック
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // 追加フィールドを保存
    add_additional_job_fields($post_id);
}, 15);


// JavaScriptとCSSを登録・読み込むための関数
function register_job_search_scripts() {
    // URLパラメータを追加して、キャッシュを防止
    $version = '1.0.0';
    
    // スタイルシートの登録（必要に応じて）
    wp_register_style('job-search-style', get_stylesheet_directory_uri() . '/css/job-search.css', array(), $version);
    wp_enqueue_style('job-search-style');
    
    // JavaScriptの登録
    wp_register_script('job-search', get_stylesheet_directory_uri() . '/js/job-search.js', array('jquery'), $version, true);
    
    // JavaScriptにパラメータを渡す
    wp_localize_script('job-search', 'job_search_params', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'site_url' => home_url(),
        'nonce' => wp_create_nonce('job_search_nonce')
    ));
    
    // JavaScriptを読み込む
    wp_enqueue_script('job-search');
}
add_action('wp_enqueue_scripts', 'register_job_search_scripts');



/**
 * 退会処理の実装
 */

// 退会処理のアクションフックを追加
add_action('admin_post_delete_my_account', 'handle_delete_account');

/**
 * ユーザーアカウント削除処理
 */
function handle_delete_account() {
    // ログインチェック
    if (!is_user_logged_in()) {
        wp_redirect(wp_login_url());
        exit;
    }
    
    // nonceチェック
    if (!isset($_POST['delete_account_nonce']) || !wp_verify_nonce($_POST['delete_account_nonce'], 'delete_account_action')) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 退会確認チェックボックスが選択されているか確認
    if (!isset($_POST['confirm_deletion'])) {
        wp_redirect(add_query_arg('error', 'no_confirmation', home_url('/withdrawal/')));
        exit;
    }
    
    // 現在のユーザー情報を取得
    $current_user = wp_get_current_user();
    $user_email = $current_user->user_email;
    $user_name = $current_user->display_name;
    $user_id = $current_user->ID;
    
    // 退会完了メールを送信
    send_account_deletion_email($user_email, $user_name);
    
    // ユーザーをログアウト
    wp_logout();
    
    // ユーザーアカウントを削除
    // WP-Membersのユーザー削除APIがあれば使用する
    if (function_exists('wpmem_delete_user')) {
        wpmem_delete_user($user_id);
    } else {
        // WP標準のユーザー削除機能を使用
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($user_id);
    }
    
    // 退会完了ページへリダイレクト
    wp_redirect(home_url('/?account_deleted=true'));
    exit;
}

/**
 * 退会完了メールを送信する
 *
 * @param string $user_email 退会するユーザーのメールアドレス
 * @param string $user_name  退会するユーザーの表示名
 */
function send_account_deletion_email($user_email, $user_name) {
    $site_name = get_bloginfo('name');
    $admin_email = get_option('admin_email');
    
    // メールの件名
    $subject = sprintf('[%s] 退会手続き完了のお知らせ', $site_name);
    
    // メールの本文
    $message = sprintf(
        '%s 様
        
退会手続きが完了しました。

%s をご利用いただき、誠にありがとうございました。
アカウント情報および関連データはすべて削除されました。

またのご利用をお待ちしております。

------------------------------
%s
%s',
        $user_name,
        $site_name,
        $site_name,
        home_url()
    );
    
    // メールヘッダー
    $headers = array(
        'From: ' . $site_name . ' <' . $admin_email . '>',
        'Content-Type: text/plain; charset=UTF-8'
    );
    
    // メール送信
    wp_mail($user_email, $subject, $message, $headers);
    
    // 管理者にも通知
    $admin_subject = sprintf('[%s] ユーザー退会通知', $site_name);
    $admin_message = sprintf(
        '以下のユーザーが退会しました:
        
ユーザー名: %s
メールアドレス: %s
退会日時: %s',
        $user_name,
        $user_email,
        current_time('Y-m-d H:i:s')
    );
    
    wp_mail($admin_email, $admin_subject, $admin_message, $headers);
}

/**
 * トップページに退会完了メッセージを表示
 */
function show_account_deleted_message() {
    if (isset($_GET['account_deleted']) && $_GET['account_deleted'] === 'true') {
        echo '<div class="account-deleted-message">';
        echo '<p><strong>退会手続きが完了しました。ご利用ありがとうございました。</strong></p>';
        echo '</div>';
        
        // スタイルを追加
        echo '<style>
        .account-deleted-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            border-left: 4px solid #28a745;
        }
        </style>';
    }
}
add_action('wp_body_open', 'show_account_deleted_message');




/**
 * WordPressログイン画面とパスワードリセット画面のカスタマイズ
 */

// ログイン画面に独自のスタイルを適用
add_action('login_enqueue_scripts', 'custom_login_styles');

function custom_login_styles() {
    ?>
    <style type="text/css">
        /* 全体のスタイル */
        body.login {
            background-color: #f8f9fa;
        }
        
        /* WordPressロゴを非表示 */
        #login h1 a {
            display: none;
        }
        
        /* フォーム全体の調整 */
        #login {
            width: 400px;
            padding: 5% 0 0;
        }
        
        /* 見出しを追加 */
        #login:before {
            content: "ログイン";
            display: block;
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        
        /* フォームのスタイル */
        .login form {
            margin-top: 20px;
            padding: 26px 24px 34px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        /* ラベルとフォーム要素 */
        .login label {
            font-size: 14px;
            color: #333;
            font-weight: bold;
        }
        
        .login form .input {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            margin: 5px 0 15px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        /* ボタンスタイル */
        .login .button-primary {
            background-color: #0073aa;
            border-color: #0073aa;
            color: white;
            width: 100%;
            padding: 10px;
            text-shadow: none;
            box-shadow: none;
            border-radius: 4px;
            font-size: 16px;
            height: auto;
            line-height: normal;
            text-transform: none;
        }
        
        .login .button-primary:hover {
            background-color: #005f8a;
            border-color: #005f8a;
        }
        
        /* リンクのスタイル */
        #nav, #backtoblog {
            text-align: center;
            margin: 16px 0 0;
            font-size: 14px;
        }
        
        #nav a, #backtoblog a {
            color: #0073aa;
            text-decoration: none;
        }
        
        #nav a:hover, #backtoblog a:hover {
            color: #005f8a;
            text-decoration: underline;
        }
        
        /* メッセージスタイル */
        .login .message,
        .login #login_error {
            border-radius: 4px;
        }
        
        /* 余計な要素を非表示 */
        .login .privacy-policy-page-link {
            display: none;
        }
        
        /* パスワード強度インジケータを非表示 */
        .pw-weak {
            display: none !important;
        }
        
        /* パスワードリセット画面専用のスタイル */
        body.login-action-rp form p:first-child,
        body.login-action-resetpass form p:first-child {
            font-size: 14px;
            color: #333;
        }
        
        /* 文言を日本語化（CSSのcontentで置き換え） */
        body.login-action-lostpassword form p:first-child {
            display: none;  /* 元のテキストを非表示 */
        }
        
        body.login-action-lostpassword form:before {
            content: "メールアドレスを入力してください。パスワードリセット用のリンクをメールでお送りします。";
            display: block;
            margin-bottom: 15px;
            font-size: 14px;
            color: #333;
        }
        
        body.login-action-rp form:before,
        body.login-action-resetpass form:before {
            content: "新しいパスワードを設定してください。";
            display: block;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: bold;
            color: #333;
        }
    </style>
    <?php
}

// ログイン画面のタイトルを変更
add_filter('login_title', 'custom_login_title', 10, 2);

function custom_login_title($title, $url) {
    if (isset($_GET['action']) && $_GET['action'] == 'lostpassword') {
        return 'パスワード再設定 | ' . get_bloginfo('name');
    } elseif (isset($_GET['action']) && ($_GET['action'] == 'rp' || $_GET['action'] == 'resetpass')) {
        return '新しいパスワードの設定 | ' . get_bloginfo('name');
    }
    return $title;
}

// ログイン画面のテキストを日本語化
add_filter('gettext', 'custom_login_text', 20, 3);

function custom_login_text($translated_text, $text, $domain) {
    if ($domain == 'default') {
        switch ($text) {
            // パスワードリセット関連
            case 'Enter your username or email address and you will receive a link to create a new password via email.':
                $translated_text = 'メールアドレスを入力してください。パスワードリセット用のリンクをメールでお送りします。';
                break;
            case 'Username or Email Address':
                $translated_text = 'メールアドレス';
                break;
            case 'Get New Password':
                $translated_text = 'パスワード再設定メールを送信';
                break;
            case 'A password reset email has been sent to the email address on file for your account, but may take several minutes to show up in your inbox. Please wait at least 10 minutes before attempting another reset.':
                $translated_text = 'パスワード再設定用のメールを送信しました。メールが届くまで数分かかる場合があります。10分以上経ってもメールが届かない場合は、再度試してください。';
                break;
            case 'There is no account with that username or email address.':
                $translated_text = '入力されたメールアドレスのアカウントが見つかりません。';
                break;
            
            // パスワード設定画面関連
            case 'Enter your new password below or generate one.':
            case 'Enter your new password below.':
                $translated_text = '新しいパスワードを入力してください。';
                break;
            case 'New password':
                $translated_text = '新しいパスワード';
                break;
            case 'Confirm new password':
                $translated_text = '新しいパスワード（確認）';
                break;
            case 'Reset Password':
                $translated_text = 'パスワードを変更';
                break;
            case 'Your password has been reset. <a href="%s">Log in</a>':
                $translated_text = 'パスワードが変更されました。<a href="%s">ログイン</a>してください。';
                break;
            
            // その他のリンク
            case 'Log in':
                $translated_text = 'ログイン';
                break;
            case '&larr; Back to %s':
                $translated_text = 'トップページに戻る';
                break;
        }
    }
    return $translated_text;
}

// パスワードリセットメールのカスタマイズ
add_filter('retrieve_password_message', 'custom_password_reset_email', 10, 4);
add_filter('retrieve_password_title', 'custom_password_reset_email_title', 10, 1);

function custom_password_reset_email_title($title) {
    $site_name = get_bloginfo('name');
    return '[' . $site_name . '] パスワード再設定のご案内';
}

function custom_password_reset_email($message, $key, $user_login, $user_data) {
    $site_name = get_bloginfo('name');
    
    // リセットURL
    $reset_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');
    
    // メール本文
    $message = $user_data->display_name . " 様\r\n\r\n";
    $message .= "パスワード再設定のリクエストを受け付けました。\r\n\r\n";
    $message .= "以下のリンクをクリックして、新しいパスワードを設定してください：\r\n";
    $message .= $reset_url . "\r\n\r\n";
    $message .= "このリンクは24時間のみ有効です。\r\n\r\n";
    $message .= "リクエストに心当たりがない場合は、このメールを無視してください。\r\n\r\n";
    $message .= "------------------------------\r\n";
    $message .= $site_name . "\r\n";
    
    return $message;
}

// パスワード変更後のリダイレクト先を変更
add_action('login_form_resetpass', 'redirect_after_password_reset');

function redirect_after_password_reset() {
    if ('POST' === $_SERVER['REQUEST_METHOD']) {
        add_filter('login_redirect', 'custom_password_reset_redirect', 10, 3);
    }
}

function custom_password_reset_redirect($redirect_to, $requested_redirect_to, $user) {
    return home_url('/login/?password-reset=success');
}

// functions.php に追加
function custom_job_post_link($permalink, $post) {
    if ($post->post_type !== 'job') {
        return $permalink;
    }
    
    // 地域と職種のタクソノミーを取得
    $location_terms = get_the_terms($post->ID, 'job_location');
    $position_terms = get_the_terms($post->ID, 'job_position');
    
    $location_slug = $location_terms && !is_wp_error($location_terms) ? $location_terms[0]->slug : 'area';
    $position_slug = $position_terms && !is_wp_error($position_terms) ? $position_terms[0]->slug : 'position';
    
    // 新しいURLパターンを構築
    $permalink = home_url('/jobs/' . $location_slug . '/' . $position_slug . '/' . $post->ID . '/');
    
    return $permalink;
}
add_filter('post_type_link', 'custom_job_post_link', 10, 2);

// functions.php に追加
function add_custom_job_rewrite_rules() {
    add_rewrite_rule(
        'jobs/([^/]+)/([^/]+)/([0-9]+)/?$',
        'index.php?post_type=job&p=$matches[3]',
        'top'
    );
    
    // 地域別一覧ページ
    add_rewrite_rule(
        'jobs/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]',
        'top'
    );
    
    // 職種別一覧ページ
    add_rewrite_rule(
        'jobs/position/([^/]+)/?$',
        'index.php?post_type=job&job_position=$matches[1]',
        'top'
    );
	
    // 基本の求人アーカイブページ用のルール
    add_rewrite_rule(
        'jobs/?$',
        'index.php?post_type=job',
        'top'
    );
	
}
add_action('init', 'add_custom_job_rewrite_rules');


function breadcrumb() {
    echo '<div class="breadcrumb">';
    echo '<a href="'.home_url().'">ホーム</a> &gt; ';
    
    if (is_single()) {
        $categories = get_the_category();
        if ($categories) {
            echo '<a href="'.get_category_link($categories[0]->term_id).'">'.$categories[0]->name.'</a> &gt; ';
        }
        echo get_the_title();
    } elseif (is_page()) {
        echo get_the_title();
    } elseif (is_category()) {
        echo single_cat_title('', false);
    }
    
    echo '</div>';
}

/**
 * お気に入り求人機能 - 統合版
 * functions.phpに追加してください
 */

// === JavaScript読み込み機能 ===
function enqueue_favorite_job_scripts() {
    // スクリプトを登録して読み込む
    wp_register_script('favorite-job-script', get_stylesheet_directory_uri() . '/js/favorite-job.js', array('jquery'), '1.0.0', true);
    
    // ローカライズスクリプトを追加（ajaxurl、nonceなどの値をJSに渡す）
    wp_localize_script('favorite-job-script', 'favoriteJobSettings', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'home_url' => home_url('/jobs/'),
        'nonce' => wp_create_nonce('job_favorite_nonce')
    ));
    
    // スクリプトを読み込む
    wp_enqueue_script('favorite-job-script');
}
add_action('wp_enqueue_scripts', 'enqueue_favorite_job_scripts');

// === お気に入り求人の追加・削除処理 ===
function handle_toggle_job_favorite() {
    // ナンス検証（複数のnonceに対応）
    $nonce_keys = array('job_favorite_nonce', 'favorites_nonce');
    $nonce_valid = false;
    
    if (isset($_POST['nonce'])) {
        foreach ($nonce_keys as $key) {
            if (wp_verify_nonce($_POST['nonce'], $key)) {
                $nonce_valid = true;
                break;
            }
        }
    }
    
    if (!$nonce_valid) {
        wp_send_json_error(array('message' => 'セキュリティチェックに失敗しました。'));
        return;
    }
    
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'ログインが必要です。'));
        return;
    }
    
    $user_id = get_current_user_id();
    $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
    
    if (!$job_id) {
        wp_send_json_error(array('message' => '無効な求人IDです。'));
        return;
    }
    
    // 現在のお気に入りリストを取得
    $favorites = get_user_meta($user_id, 'user_favorites', true);
    
    if (!is_array($favorites)) {
        $favorites = array();
    }
    
    // お気に入りリストに含まれているかチェック
    $index = array_search($job_id, $favorites);
    
    if ($index !== false) {
        // お気に入りリストに含まれている場合は削除
        unset($favorites[$index]);
        $favorites = array_values($favorites); // インデックスを振り直し
        update_user_meta($user_id, 'user_favorites', $favorites);
        wp_send_json_success(array(
            'status' => 'removed',
            'favorited' => false,
            'message' => 'お気に入りから削除しました。'
        ));
    } else {
        // お気に入りリストに含まれていない場合は追加
        $favorites[] = $job_id;
        update_user_meta($user_id, 'user_favorites', $favorites);
        wp_send_json_success(array(
            'status' => 'added',
            'favorited' => true,
            'message' => 'お気に入りに追加しました。'
        ));
    }
}

// フックの登録（ログイン・非ログイン両方に対応）
add_action('wp_ajax_toggle_job_favorite', 'handle_toggle_job_favorite');
add_action('wp_ajax_nopriv_toggle_job_favorite', 'handle_toggle_job_favorite');

/**
 * ショートコードを追加 - キープ(お気に入り)した求人の数を表示
 * 使用例: [favorite_jobs_count]
 */
function favorite_jobs_count_shortcode() {
    if (!is_user_logged_in()) {
        return '0';
    }
    
    $user_id = get_current_user_id();
    $favorites = get_user_meta($user_id, 'user_favorites', true);
    
    if (!is_array($favorites)) {
        return '0';
    }
    
    return count($favorites);
}
add_shortcode('favorite_jobs_count', 'favorite_jobs_count_shortcode');
/**
 * お気に入り求人機能 - 互換性対応版
 * functions.phpに追加してください
 */

// === JavaScript読み込み機能 ===
if (!function_exists('enqueue_favorite_job_scripts')) {
    function enqueue_favorite_job_scripts() {
        // スクリプトを登録して読み込む
        wp_register_script('favorite-job-script', get_stylesheet_directory_uri() . '/js/favorite-job.js', array('jquery'), '1.0.0', true);
        
        // ローカライズスクリプトを追加
        wp_localize_script('favorite-job-script', 'favoriteJobSettings', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'home_url' => home_url('/jobs/'),
            'nonce' => wp_create_nonce('job_favorite_nonce')
        ));
        
        // スクリプトを読み込む
        wp_enqueue_script('favorite-job-script');
    }
}
add_action('wp_enqueue_scripts', 'enqueue_favorite_job_scripts');

// === お気に入り求人の追加・削除処理 ===
if (!function_exists('handle_toggle_job_favorite')) {
    function handle_toggle_job_favorite() {
        // nonceチェック
        $is_valid_nonce = false;
        
        if (isset($_POST['nonce'])) {
            // job_favorite_nonceのチェック
            if (wp_verify_nonce($_POST['nonce'], 'job_favorite_nonce')) {
                $is_valid_nonce = true;
            }
            
            // favorites_nonceのチェック
            if (!$is_valid_nonce && wp_verify_nonce($_POST['nonce'], 'favorites_nonce')) {
                $is_valid_nonce = true;
            }
        }
        
        if (!$is_valid_nonce) {
            wp_send_json_error(array('message' => 'セキュリティチェックに失敗しました。'));
            return;
        }
        
        // ログインチェック
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'ログインが必要です。'));
            return;
        }
        
        // ユーザーIDと求人IDの取得
        $user_id = get_current_user_id();
        $job_id = 0;
        
        if (isset($_POST['job_id']) && $_POST['job_id']) {
            $job_id = intval($_POST['job_id']);
        }
        
        if (!$job_id) {
            wp_send_json_error(array('message' => '無効な求人IDです。'));
            return;
        }
        
        // 現在のお気に入りリストを取得
        $favorites = get_user_meta($user_id, 'user_favorites', true);
        
        if (empty($favorites) || !is_array($favorites)) {
            $favorites = array();
        }
        
        // お気に入りリストに含まれているかチェック
        $index = array_search($job_id, $favorites);
        
        if ($index !== false) {
            // お気に入りリストに含まれている場合は削除
            unset($favorites[$index]);
            $favorites = array_values($favorites); // インデックスを振り直し
            update_user_meta($user_id, 'user_favorites', $favorites);
            
            $result = array(
                'status' => 'removed',
                'favorited' => false,
                'message' => 'お気に入りから削除しました。'
            );
            
            wp_send_json_success($result);
        } else {
            // お気に入りリストに含まれていない場合は追加
            $favorites[] = $job_id;
            update_user_meta($user_id, 'user_favorites', $favorites);
            
            $result = array(
                'status' => 'added',
                'favorited' => true,
                'message' => 'お気に入りに追加しました。'
            );
            
            wp_send_json_success($result);
        }
    }
}

// フックの登録（ログイン・非ログイン両方に対応）
remove_action('wp_ajax_toggle_job_favorite', 'toggle_job_favorite_handler'); // 既存のハンドラーを削除（もし存在すれば）
add_action('wp_ajax_toggle_job_favorite', 'handle_toggle_job_favorite');
add_action('wp_ajax_nopriv_toggle_job_favorite', 'handle_toggle_job_favorite');

/**
 * ショートコードを追加 - キープ(お気に入り)した求人の数を表示
 * 使用例: [favorite_jobs_count]
 */
if (!function_exists('favorite_jobs_count_shortcode')) {
    function favorite_jobs_count_shortcode() {
        if (!is_user_logged_in()) {
            return '0';
        }
        
        $user_id = get_current_user_id();
        $favorites = get_user_meta($user_id, 'user_favorites', true);
        
        if (empty($favorites) || !is_array($favorites)) {
            return '0';
        }
        
        return (string)count($favorites);
    }
}
add_shortcode('favorite_jobs_count', 'favorite_jobs_count_shortcode');
/**
 * 検索結果ページにおいて、カスタム投稿タイプ「job」のみを表示する
 */
function job_custom_search_filter($query) {
    if (!is_admin() && $query->is_main_query() && $query->is_search) {
        // フロントエンドの検索結果ページでのみ実行
        $query->set('post_type', 'job');
    }
    return $query;
}
add_filter('pre_get_posts', 'job_custom_search_filter');

/**
 * キーワード検索を拡張して、カスタムフィールドも検索対象に含める
 */
function job_custom_search_where($where, $query) {
    global $wpdb;
    
    if (!is_admin() && $query->is_main_query() && $query->is_search) {
        $search_term = get_search_query();
        
        if (!empty($search_term)) {
            // オリジナルの検索条件を保持
            $original_where = $where;
            
            // カスタムフィールドを検索対象に追加
            $custom_fields = array(
                'facility_name',
                'facility_company',
                'facility_address',
                'job_content_title',
                'salary_range',
                'requirements',
                'benefits'
            );
            
            $meta_query = array();
            foreach ($custom_fields as $field) {
                $meta_query[] = $wpdb->prepare("(pm.meta_key = %s AND pm.meta_value LIKE %s)", $field, '%' . $wpdb->esc_like($search_term) . '%');
            }
            
            // メタデータとのJOINを確実にするためにクエリを調整
            // 注意：このアプローチは複雑なため、実際の環境でよく確認してください
            if (!empty($meta_query)) {
                $meta_where = ' OR (' . implode(' OR ', $meta_query) . ')';
                
                // 基本的な検索句の正規表現を使用して置換
                $pattern = '/([\(])\s*' . $wpdb->posts . '\.post_title\s+LIKE\s*(\'[^\']*\')\s*\)/';
                if (preg_match($pattern, $where, $matches)) {
                    $where = str_replace($matches[0], $matches[0] . $meta_where, $where);
                }
            }
        }
    }
    
    return $where;
}
add_filter('posts_where', 'job_custom_search_where', 10, 2);

/**
 * カスタムフィールド検索のためのJOINを追加
 */
function job_custom_search_join($join, $query) {
    global $wpdb;
    
    if (!is_admin() && $query->is_main_query() && $query->is_search) {
        $search_term = get_search_query();
        
        if (!empty($search_term)) {
            $join .= " LEFT JOIN $wpdb->postmeta pm ON ($wpdb->posts.ID = pm.post_id) ";
        }
    }
    
    return $join;
}
add_filter('posts_join', 'job_custom_search_join', 10, 2);

/**
 * 検索結果が重複しないようにする
 */
function job_custom_search_distinct($distinct, $query) {
    if (!is_admin() && $query->is_main_query() && $query->is_search) {
        return "DISTINCT";
    }
    
    return $distinct;
}
add_filter('posts_distinct', 'job_custom_search_distinct', 10, 2);


// スライダーカスタム投稿タイプの登録
function register_slider_post_type() {
    $labels = array(
        'name'                  => 'スライダー',
        'singular_name'         => 'スライド',
        'menu_name'             => 'スライダー',
        'name_admin_bar'        => 'スライド',
        'archives'              => 'スライドアーカイブ',
        'attributes'            => 'スライド属性',
        'all_items'             => 'すべてのスライド',
        'add_new_item'          => '新しいスライドを追加',
        'add_new'               => '新規追加',
        'new_item'              => '新しいスライド',
        'edit_item'             => 'スライドを編集',
        'update_item'           => 'スライドを更新',
        'view_item'             => 'スライドを表示',
        'view_items'            => 'スライドを表示',
        'search_items'          => 'スライドを検索',
    );
    
    $args = array(
        'label'                 => 'スライド',
        'labels'                => $labels,
        'supports'              => array('title'),  // タイトルのみサポート
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 20,
        'menu_icon'             => 'dashicons-images-alt2',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
        'show_in_rest'          => true,
    );
    
    register_post_type('slide', $args);
}
add_action('init', 'register_slider_post_type');

// スライド用のカスタムフィールドを追加
function slider_custom_meta_boxes() {
    add_meta_box(
        'slider_settings',
        'スライド設定',
        'slider_settings_callback',
        'slide',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'slider_custom_meta_boxes');

// スライド設定のコールバック関数
function slider_settings_callback($post) {
    wp_nonce_field(basename(__FILE__), 'slider_nonce');
    
    // 保存された値を取得
    $slide_image_id = get_post_meta($post->ID, 'slide_image_id', true);
    $slide_image_url = wp_get_attachment_image_url($slide_image_id, 'full');
    $slide_link = get_post_meta($post->ID, 'slide_link', true);
    
    ?>
    <div class="slider-settings-container" style="margin-bottom: 20px;">
        <p>
            <label for="slide_image"><strong>スライド画像：</strong></label><br>
            <input type="hidden" name="slide_image_id" id="slide_image_id" value="<?php echo esc_attr($slide_image_id); ?>" />
            <button type="button" class="button" id="slide_image_button">画像を選択</button>
            <button type="button" class="button" id="slide_image_remove" style="<?php echo empty($slide_image_id) ? 'display:none;' : ''; ?>">画像を削除</button>
            
            <div id="slide_image_preview" style="margin-top: 10px; <?php echo empty($slide_image_url) ? 'display:none;' : ''; ?>">
                <img src="<?php echo esc_url($slide_image_url); ?>" alt="スライド画像" style="max-width: 300px; height: auto;" />
            </div>
        </p>
        
        <p>
            <label for="slide_link"><strong>スライドリンク：</strong></label><br>
            <input type="url" name="slide_link" id="slide_link" value="<?php echo esc_url($slide_link); ?>" style="width: 100%;" />
            <span class="description">スライドをクリックした時に移動するURLを入力してください。空白の場合はリンクしません。</span>
        </p>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // 画像選択ボタンのクリックイベント
        $('#slide_image_button').click(function(e) {
            e.preventDefault();
            
            var image_frame;
            
            // MediaUploader インスタンスが既に存在する場合は再利用
            if (image_frame) {
                image_frame.open();
                return;
            }
            
            // MediaUploader の設定と作成
            image_frame = wp.media({
                title: 'スライド画像を選択',
                button: {
                    text: '画像を使用'
                },
                multiple: false
            });
            
            // 画像が選択されたときの処理
            image_frame.on('select', function() {
                var attachment = image_frame.state().get('selection').first().toJSON();
                $('#slide_image_id').val(attachment.id);
                
                // プレビュー更新
                $('#slide_image_preview img').attr('src', attachment.url);
                $('#slide_image_preview').show();
                $('#slide_image_remove').show();
            });
            
            // MediaUploader を開く
            image_frame.open();
        });
        
        // 画像削除ボタンのクリックイベント
        $('#slide_image_remove').click(function(e) {
            e.preventDefault();
            $('#slide_image_id').val('');
            $('#slide_image_preview').hide();
            $(this).hide();
        });
    });
    </script>
    <?php
}

// スライド設定を保存
function save_slider_meta($post_id) {
    // 自動保存の場合は処理しない
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    
    // nonce を確認
    if (!isset($_POST['slider_nonce']) || !wp_verify_nonce($_POST['slider_nonce'], basename(__FILE__))) return;
    
    // 権限を確認
    if (!current_user_can('edit_post', $post_id)) return;
    
    // スライド画像IDを保存
    if (isset($_POST['slide_image_id'])) {
        update_post_meta($post_id, 'slide_image_id', sanitize_text_field($_POST['slide_image_id']));
    }
    
    // スライドリンクを保存
    if (isset($_POST['slide_link'])) {
        update_post_meta($post_id, 'slide_link', esc_url_raw($_POST['slide_link']));
    }
}
add_action('save_post_slide', 'save_slider_meta');

// MediaUploader のスクリプトを読み込む
function slider_admin_scripts() {
    global $post_type;
    if ('slide' === $post_type) {
        wp_enqueue_media();
    }
}
add_action('admin_enqueue_scripts', 'slider_admin_scripts');




// functions.phpに追加
function custom_wpmem_login_redirect($redirect_to, $user) {
    // 特定のページからのログインかどうかをチェック
    if (isset($_POST['is_franchise_login']) && $_POST['is_franchise_login'] === '1') {
        return 'https://recruitment.kodomo-plus.co.jp/job-list/';
    }
    return $redirect_to;
}
add_filter('wpmem_login_redirect', 'custom_wpmem_login_redirect', 10, 2);

/**
 * メルマガ関連機能の実装
 */

// メルマガ購読者一覧ページを管理メニューに追加
function add_mailmagazine_subscribers_menu() {
    add_menu_page(
        'メルマガ購読者一覧', // ページタイトル
        'メルマガリスト', // メニュータイトル
        'manage_options', // 権限
        'mailmagazine-subscribers', // メニュースラッグ
        'display_mailmagazine_subscribers', // 表示用の関数
        'dashicons-email-alt', // アイコン
        26 // 位置
    );
}
add_action('admin_menu', 'add_mailmagazine_subscribers_menu');

// メルマガ購読者一覧ページの表示
function display_mailmagazine_subscribers() {
    // 管理者権限チェック
    if (!current_user_can('manage_options')) {
        wp_die('アクセス権限がありません。');
    }
    
    // CSVエクスポート処理
    if (isset($_POST['export_csv']) && isset($_POST['mailmagazine_export_nonce']) && 
        wp_verify_nonce($_POST['mailmagazine_export_nonce'], 'mailmagazine_export_action')) {
        
        // 出力バッファリングを無効化（既に開始されている場合は終了）
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // CSVのヘッダー設定
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="mailmagazine_subscribers_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOMを出力（Excelでの文字化け対策）
        fputs($output, "\xEF\xBB\xBF");
        
        // ヘッダー行 - 指定された順序で
        fputcsv($output, array('登録日', '名前', 'メールアドレス', '職種', 'ご住所(都道府県)'));
        
        // 購読者を取得
        $subscribers = get_mailmagazine_subscribers();
        
        foreach ($subscribers as $user) {
            // 職種情報を取得
            $jobtype = get_user_meta($user->ID, 'jobtype', true);
            $jobtype_display = !empty($jobtype) ? $jobtype : '';
            
            // 都道府県情報を取得
            $prefecture = get_user_meta($user->ID, 'prefectures', true);
            $prefecture_display = !empty($prefecture) ? $prefecture : '';
            
            fputcsv($output, array(
                date('Y/m/d', strtotime($user->user_registered)),
                $user->display_name,
                $user->user_email,
                $jobtype_display,
                $prefecture_display
            ));
        }
        
        fclose($output);
        exit;
    }
    
    // 購読者を取得
    $subscribers = get_mailmagazine_subscribers();
    $total_subscribers = count($subscribers);
    
    // 管理画面の表示
    ?>
    <div class="wrap">
        <h1>メルマガ購読者一覧</h1>
        
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="post">
                    <?php wp_nonce_field('mailmagazine_export_action', 'mailmagazine_export_nonce'); ?>
                    <input type="submit" name="export_csv" class="button action" value="CSVでエクスポート">
                </form>
            </div>
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo $total_subscribers; ?> 件の購読者</span>
            </div>
            <br class="clear">
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-registered">登録日</th>
                    <th scope="col" class="manage-column column-name">名前</th>
                    <th scope="col" class="manage-column column-email">メールアドレス</th>
                    <th scope="col" class="manage-column column-jobtype">職種</th>
                    <th scope="col" class="manage-column column-prefecture">ご住所(都道府県)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (empty($subscribers)) {
                    echo '<tr><td colspan="5">購読者はいません。</td></tr>';
                } else {
                    foreach ($subscribers as $user) {
                        // 職種情報を取得
                        $jobtype = get_user_meta($user->ID, 'jobtype', true);
                        $jobtype_display = !empty($jobtype) ? $jobtype : '未設定';
                        
                        // 都道府県情報を取得
                        $prefecture = get_user_meta($user->ID, 'prefectures', true);
                        $prefecture_display = !empty($prefecture) ? $prefecture : '未設定';
                        ?>
                        <tr>
                            <td><?php echo date('Y/m/d', strtotime($user->user_registered)); ?></td>
                            <td>
                                <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>">
                                    <?php echo esc_html($user->display_name); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html($jobtype_display); ?></td>
                            <td><?php echo esc_html($prefecture_display); ?></td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="col" class="manage-column column-registered">登録日</th>
                    <th scope="col" class="manage-column column-name">名前</th>
                    <th scope="col" class="manage-column column-email">メールアドレス</th>
                    <th scope="col" class="manage-column column-jobtype">職種</th>
                    <th scope="col" class="manage-column column-prefecture">ご住所(都道府県)</th>
                </tr>
            </tfoot>
        </table>
        
        <style>
        .column-registered { width: 10%; }
        .column-name { width: 20%; }
        .column-email { width: 30%; }
        .column-jobtype { width: 20%; }
        .column-prefecture { width: 20%; }
        </style>
    </div>
    <?php
}
/**
 * 別の方法でCSVをダウンロードする専用のアクション
 */
function mailmagazine_download_csv_action() {
    // 管理者権限チェック
    if (!current_user_can('manage_options')) {
        wp_die('アクセス権限がありません。');
    }
    
    // nonceチェック
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'download_mailmagazine_csv')) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 出力バッファリングを無効化
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // CSVのヘッダー設定
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="mailmagazine_subscribers_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // BOMを出力（Excelでの文字化け対策）
    fputs($output, "\xEF\xBB\xBF");
    
    // ヘッダー行
    fputcsv($output, array('登録日', '名前', 'メールアドレス'));
    
    // 購読者を取得
    $subscribers = get_mailmagazine_subscribers();
    
    foreach ($subscribers as $user) {
        fputcsv($output, array(
            date('Y/m/d', strtotime($user->user_registered)),
            $user->display_name,
            $user->user_email
        ));
    }
    
    fclose($output);
    exit;
}
add_action('admin_post_download_mailmagazine_csv', 'mailmagazine_download_csv_action');

/**
 * メルマガを購読しているユーザーを取得する関数
 */
function get_mailmagazine_subscribers() {
    // ユーザークエリパラメータ
    $args = array(
        'meta_key'     => 'mailmagazine_preference',
        'meta_value'   => 'subscribe',
        'fields'       => array('ID', 'user_email', 'display_name', 'user_registered')
    );
    
    // クエリ実行
    $subscribers = get_users($args);
    
    return $subscribers;
}

/**
 * 新規ユーザー登録時にメルマガ設定のデフォルト値を設定
 * 権限ごとに異なるデフォルト値を設定
 */
function set_default_mailmagazine_preference($user_id) {
    // ユーザーの権限を取得
    $user = get_userdata($user_id);
    
    // デフォルト値を権限によって設定
    if (in_array('subscriber', (array)$user->roles)) {
        // 購読者(subscriber)の場合は「購読する」をデフォルトに設定
        add_user_meta($user_id, 'mailmagazine_preference', 'subscribe', true);
    } elseif (in_array('agency', (array)$user->roles)) {
        // 加盟教室(agency)の場合は「購読しない」をデフォルトに設定
        add_user_meta($user_id, 'mailmagazine_preference', 'unsubscribe', true);
    } else {
        // その他の権限の場合も「購読しない」をデフォルトに設定
        add_user_meta($user_id, 'mailmagazine_preference', 'unsubscribe', true);
    }
}
add_action('user_register', 'set_default_mailmagazine_preference');
/**
 * ユーザープロフィール画面にメルマガ設定フィールドを追加
 */
function add_mailmagazine_preference_field($user) {
    // 現在の設定を取得
    $mailmagazine_preference = get_user_meta($user->ID, 'mailmagazine_preference', true);
    if (empty($mailmagazine_preference)) {
        $mailmagazine_preference = 'unsubscribe'; // デフォルト値
    }
    ?>
    <h3>メルマガ設定</h3>
    <table class="form-table">
        <tr>
            <th><label for="mailmagazine_preference">メルマガ購読</label></th>
            <td>
                <select name="mailmagazine_preference" id="mailmagazine_preference">
                    <option value="subscribe" <?php selected($mailmagazine_preference, 'subscribe'); ?>>購読する</option>
                    <option value="unsubscribe" <?php selected($mailmagazine_preference, 'unsubscribe'); ?>>購読しない</option>
                </select>
                <p class="description">メールマガジンの購読設定を選択してください。</p>
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'add_mailmagazine_preference_field');
add_action('edit_user_profile', 'add_mailmagazine_preference_field');

/**
 * ユーザープロフィール更新時にメルマガ設定を保存
 */
function save_mailmagazine_preference_field($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }
    
    if (isset($_POST['mailmagazine_preference'])) {
        update_user_meta($user_id, 'mailmagazine_preference', sanitize_text_field($_POST['mailmagazine_preference']));
    }
}
add_action('personal_options_update', 'save_mailmagazine_preference_field');
add_action('edit_user_profile_update', 'save_mailmagazine_preference_field');


/**
 * ユーザーが加盟教室(agency)グループに所属しているかチェックする関数
 */
function is_agency_user() {
    // ユーザーがログインしているか確認
    if (!is_user_logged_in()) {
        return false;
    }
    
    // 現在のユーザー情報を取得
    $user = wp_get_current_user();
    
    // WordPress標準のロールで'agency'を持っているか確認
    return in_array('agency', (array) $user->roles);
}

/**
 * ヘッダーナビゲーションとページアクセスのリダイレクト処理
 */
function agency_user_redirect() {
    // agencyユーザーかどうかをチェック
    if (is_agency_user()) {
        global $wp;
        $current_url = home_url(add_query_arg(array(), $wp->request));
        
        // お気に入りページや会員ページへのアクセスを/job-list/にリダイレクト
        if (strpos($current_url, '/favorites') !== false || 
            strpos($current_url, '/members') !== false) {
            wp_redirect(home_url('/job-list/'));
            exit;
        }
    }
}
add_action('template_redirect', 'agency_user_redirect');

/**
 * ヘッダーリンク修正用のJavaScript
 */
function modify_header_links_for_agency() {
    if (is_agency_user()) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // お気に入りとマイページのリンクを/job-list/に変更
            $('.user-nav a[href*="/favorites"]').attr('href', '<?php echo home_url("/job-list/"); ?>');
            $('.user-nav a[href*="/members"]').attr('href', '<?php echo home_url("/job-list/"); ?>');
        });
        </script>
        <?php
    }
}
add_action('wp_footer', 'modify_header_links_for_agency');

/**
 * 特定のユーザーロールの管理画面アクセスを制限する
 */
function restrict_admin_access() {
    // 現在のユーザー情報を取得
    $user = wp_get_current_user();
    
    // agencyまたはsubscriberロールを持つユーザーの管理画面アクセスを制限
    if (
        !empty($user->ID) && 
        (in_array('agency', (array) $user->roles) || in_array('subscriber', (array) $user->roles))
    ) {
        // 現在のURLが管理画面かどうかを確認
        $screen = get_current_screen();
        
        // プロフィール編集画面は許可（オプション）
        if (is_admin() && (!isset($screen) || $screen->id !== 'profile')) {
            // agencyユーザーはジョブリストページへ、subscriberユーザーはホームページへリダイレクト
            if (in_array('agency', (array) $user->roles)) {
                wp_redirect(home_url('/job-list/'));
            } else {
                wp_redirect(home_url());
            }
            exit;
        }
    }
}
add_action('admin_init', 'restrict_admin_access');

/**
 * 管理バーを非表示にする
 */
function remove_admin_bar_for_specific_roles() {
    if (
        current_user_can('agency') || 
        current_user_can('subscriber')
    ) {
        show_admin_bar(false);
    }
}
add_action('after_setup_theme', 'remove_admin_bar_for_specific_roles');

/**
 * ログイン時のリダイレクト処理
 */
function custom_login_redirect($redirect_to, $request, $user) {
    // ユーザーオブジェクトが有効かチェック
    if (isset($user->roles) && is_array($user->roles)) {
        // agencyユーザーはジョブリストページへリダイレクト
        if (in_array('agency', $user->roles)) {
            return home_url('/job-list/');
        }
        // subscriberユーザーはホームページへリダイレクト
        elseif (in_array('subscriber', $user->roles)) {
            return home_url();
        }
    }
    
    // その他のユーザーは通常のリダイレクト先へ
    return $redirect_to;
}
add_filter('login_redirect', 'custom_login_redirect', 10, 3);

/**
 * AJAX リクエストのアクセス制限を行わない（フロントエンドの機能を維持するため）
 */
function allow_ajax_requests_for_all_users() {
    // 現在のリクエストがAJAXリクエストの場合は制限をバイパス
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    
    // メディアアップロードなどの特定のリクエストも許可
    $allowed_actions = array(
        'upload-attachment',
        'async-upload',
    );
    
    if (isset($_GET['action']) && in_array($_GET['action'], $allowed_actions)) {
        return;
    }
    
    // 通常の管理画面アクセス制限を適用
    restrict_admin_access();
}
add_action('admin_init', 'allow_ajax_requests_for_all_users', 0);  // 優先度0で先に実行


/**
 * 加盟教室ユーザー全員を自動的に確認済みにする関数
 * この関数はサイト読み込み時に一度だけ実行されます
 */
function confirm_all_agency_users() {
    // 既に実行済みか確認
    if (get_option('agency_users_confirmed') === 'yes') {
        return;
    }
    
    // agencyロールのユーザーを全て取得
    $agency_users = get_users(array('role' => 'agency'));
    
    if (!empty($agency_users)) {
        foreach ($agency_users as $user) {
            // 確認済みフラグを設定
            update_user_meta($user->ID, '_wpmem_user_confirmed', time());
            error_log('Agency user confirmed: ' . $user->user_email);
        }
    }
    
    // 実行済みフラグを設定
    update_option('agency_users_confirmed', 'yes');
}
add_action('init', 'confirm_all_agency_users', 1);

/**
 * WP-Membersの確認機能をより確実にバイパスする
 */
function bypass_wpmem_confirmation_check($is_confirmed, $user_id) {
    // ユーザー情報を取得
    $user = get_userdata($user_id);
    
    // agencyロールを持つユーザーの場合は常に確認済みとする
    if ($user && in_array('agency', (array) $user->roles)) {
        return true;
    }
    
    return $is_confirmed;
}
// 最も高い優先度（999）で確認チェックをフック
add_filter('wpmem_is_user_confirmed', 'bypass_wpmem_confirmation_check', 999, 2);

/**
 * ログイン処理前に確認済みステータスを設定
 */
function set_agency_confirmed_before_login() {
    // ログインフォームが送信された場合
    if (isset($_POST['log']) && isset($_POST['pwd'])) {
        // ユーザー名またはメールアドレスを取得
        $username = sanitize_user($_POST['log']);
        
        // ユーザーを特定
        $user = get_user_by('login', $username);
        if (!$user) {
            $user = get_user_by('email', $username);
        }
        
        // ユーザーが存在し、agencyロールを持っている場合
        if ($user && in_array('agency', (array) $user->roles)) {
            // 確認済みフラグを設定
            update_user_meta($user->ID, '_wpmem_user_confirmed', time());
        }
    }
}
add_action('init', 'set_agency_confirmed_before_login', 1);

/**
 * エラーメッセージを完全に抑制
 */
function remove_confirmation_error($error_msg) {
    // 確認関連のエラーメッセージを確認
    if (strpos($error_msg, 'Account not confirmed') !== false || 
        strpos($error_msg, 'confirm') !== false || 
        strpos($error_msg, '確認') !== false) {
        
        // ログインフォームが送信された場合、ユーザーを確認
        if (isset($_POST['log'])) {
            $username = sanitize_user($_POST['log']);
            $user = get_user_by('login', $username);
            if (!$user) {
                $user = get_user_by('email', $username);
            }
            
            if ($user && in_array('agency', (array) $user->roles)) {
                // agencyユーザーの場合はエラーを空にする
                return '';
            }
        }
    }
    
    return $error_msg;
}
add_filter('wpmem_login_failed', 'remove_confirmation_error', 999);
add_filter('wpmem_login_status', 'remove_confirmation_error', 999);

/**
 * 確認メールの送信を防止する
 */
function prevent_confirmation_email($email_args) {
    if (isset($email_args['user_id'])) {
        $user = get_userdata($email_args['user_id']);
        if ($user && in_array('agency', (array) $user->roles)) {
            return false;
        }
    }
    return $email_args;
}
add_filter('wpmem_email_filter', 'prevent_confirmation_email', 999);




/**
 * 検索ワード対応パンくずリスト
 */
function improved_breadcrumb() {
    // 現在のURLを取得
    $current_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $parsed_url = parse_url($current_url);
    $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
    $query = isset($parsed_url['query']) ? $parsed_url['query'] : '';
    
    // クエリパラメータを解析
    $query_params = array();
    if (!empty($query)) {
        parse_str($query, $query_params);
    }
    
    // 検索キーワードを取得（sパラメータ）
    $search_query = get_search_query();
    if (empty($search_query) && isset($query_params['s'])) {
        $search_query = $query_params['s'];
    }
    
    // パス部分を解析
    $path_parts = explode('/', trim($path, '/'));
    
    // URLパスにjobsを含むか確認
    $is_jobs_path = in_array('jobs', $path_parts);
    $jobs_index = array_search('jobs', $path_parts);
    
    // 検索条件を保存する配列
    $conditions = array();
    
    // パンくずHTMLを構築
    $breadcrumb = '<div class="breadcrumb">';
    $breadcrumb .= '<a href="' . home_url() . '">ホーム</a>';
    $breadcrumb .= ' &gt; <a href="' . home_url('/jobs/') . '">求人情報</a>';
    
    // 求人一覧リンク
    $breadcrumb .= ' &gt; <a href="' . home_url('/jobs/') . '">求人一覧</a>';
    
    // URLパスの解析（例: jobs/location/tokyo/position/nurse）
    if ($is_jobs_path && $jobs_index !== false) {
        $taxonomy_map = array(
            'location' => 'job_location',
            'position' => 'job_position',
            'type' => 'job_type',
            'facility' => 'facility_type',
            'feature' => 'job_feature'
        );
        
        $segments = array();
        
        // URLパスを解析して、タクソノミーとスラッグのペアを抽出
        for ($i = $jobs_index + 1; $i < count($path_parts) - 1; $i += 2) {
            if (isset($path_parts[$i]) && isset($path_parts[$i+1])) {
                $tax_segment = $path_parts[$i];
                $term_slug = $path_parts[$i+1];
                
                if (isset($taxonomy_map[$tax_segment])) {
                    $taxonomy = $taxonomy_map[$tax_segment];
                    $segments[] = array(
                        'segment' => $tax_segment,
                        'slug' => $term_slug,
                        'taxonomy' => $taxonomy
                    );
                }
            }
        }
        
        // パス内の条件でパンくずを構築
        foreach ($segments as $segment) {
            $term = get_term_by('slug', $segment['slug'], $segment['taxonomy']);
            
            if ($term) {
                // 階層を持つタクソノミーで親がある場合（主にlocation）
                if ($segment['segment'] == 'location' && $term->parent != 0) {
                    $parent_terms = array();
                    $parent_id = $term->parent;
                    
                    // 親ターム階層を取得
                    while ($parent_id) {
                        $parent = get_term($parent_id, $segment['taxonomy']);
                        if (is_wp_error($parent)) {
                            break;
                        }
                        $parent_terms[] = array(
                            'term' => $parent,
                            'url' => home_url('/jobs/location/' . $parent->slug . '/')
                        );
                        $parent_id = $parent->parent;
                    }
                    
                    // 親から順に表示
                    foreach (array_reverse($parent_terms) as $parent_data) {
                        $parent = $parent_data['term'];
                        $parent_url = $parent_data['url'];
                        
                        $breadcrumb .= ' &gt; <a href="' . esc_url($parent_url) . '">' . esc_html($parent->name) . '</a>';
                        
                        // 条件にも追加
                        $conditions[] = $parent->name;
                    }
                }
                
                // 現在の条件を追加
                $term_url = home_url('/jobs/' . $segment['segment'] . '/' . $term->slug . '/');
                
                // すべての条件をリンクにする
                $breadcrumb .= ' &gt; <a href="' . esc_url($term_url) . '">' . esc_html($term->name) . '</a>';
                
                // 条件に追加
                $conditions[] = $term->name;
            }
        }
        
        // クエリパラメータの解析（例: ?features[]=mikeiken&features[]=shouyo）
        if (isset($query_params['features']) && is_array($query_params['features'])) {
            // features[]パラメータを解析
            $feature_slugs = $query_params['features'];
            
            foreach ($feature_slugs as $index => $slug) {
                $term = get_term_by('slug', $slug, 'job_feature');
                if ($term && !is_wp_error($term)) {
                    // 特徴用のURLを生成
                    $feature_url = home_url('/jobs/feature/' . $term->slug . '/');
                    
                    // 個別の特徴リンクを追加
                    $breadcrumb .= ' &gt; <a href="' . esc_url($feature_url) . '">' . esc_html($term->name) . '</a>';
                    
                    // 条件に追加
                    $conditions[] = $term->name;
                }
            }
        }
    } 
    // タクソノミーアーカイブページの場合
    elseif (is_tax()) {
        $term = get_queried_object();
        $taxonomy = $term->taxonomy;
        
        // タクソノミー名からURLのセグメント部分を決定
        $tax_segment = '';
        switch ($taxonomy) {
            case 'job_location':
                $tax_segment = 'location';
                break;
            case 'job_position':
                $tax_segment = 'position';
                break;
            case 'job_type':
                $tax_segment = 'type';
                break;
            case 'facility_type':
                $tax_segment = 'facility';
                break;
            case 'job_feature':
                $tax_segment = 'feature';
                break;
        }
        
        // 階層を持つタクソノミーの場合は親も表示
        if ($term->parent != 0) {
            $parents = array();
            $parent_id = $term->parent;
            
            // 親タームを遡って配列に追加
            while ($parent_id) {
                $parent = get_term($parent_id, $taxonomy);
                if (is_wp_error($parent)) {
                    break;
                }
                $parents[] = $parent;
                $parent_id = $parent->parent;
            }
            
            // 親タームを逆順で表示（祖先→子の順）
            foreach (array_reverse($parents) as $parent) {
                // カスタム形式のURLを生成
                $parent_url = home_url('/jobs/' . $tax_segment . '/' . $parent->slug . '/');
                $breadcrumb .= ' &gt; <a href="' . esc_url($parent_url) . '">' . esc_html($parent->name) . '</a>';
                
                // 条件にも追加
                $conditions[] = $parent->name;
            }
        }
        
        // 現在のタームを追加
        $term_url = home_url('/jobs/' . $tax_segment . '/' . $term->slug . '/');
        $breadcrumb .= ' &gt; <a href="' . esc_url($term_url) . '">' . esc_html($term->name) . '</a>';
        
        // 条件にも追加
        $conditions[] = $term->name;
    }
    // 求人アーカイブページの場合
    elseif (is_post_type_archive('job') && empty($search_query)) {
        // 検索キーワードがない場合は単に「求人一覧」を現在地として表示
        // すでに「求人一覧」リンクは追加済み
    }
    // 求人詳細ページの場合
    elseif (is_singular('job')) {
        // エリア情報を階層的に表示
        $job_locations = get_the_terms(get_the_ID(), 'job_location');
        if ($job_locations && !is_wp_error($job_locations)) {
            $location = $job_locations[0];
            
            // 親タームがある場合は階層を表示
            if ($location->parent != 0) {
                $parents = array();
                $parent_id = $location->parent;
                
                while ($parent_id) {
                    $parent = get_term($parent_id, 'job_location');
                    if (is_wp_error($parent)) {
                        break;
                    }
                    $parents[] = $parent;
                    $parent_id = $parent->parent;
                }
                
                foreach (array_reverse($parents) as $parent) {
                    // カスタム形式のURLを生成
                    $parent_url = home_url('/jobs/location/' . $parent->slug . '/');
                    $breadcrumb .= ' &gt; <a href="' . esc_url($parent_url) . '">' . esc_html($parent->name) . '</a>';
                }
            }
            
            // カスタム形式のURLを生成
            $location_url = home_url('/jobs/location/' . $location->slug . '/');
            $breadcrumb .= ' &gt; <a href="' . esc_url($location_url) . '">' . esc_html($location->name) . '</a>';
        }
        
        // 職種情報
        $job_positions = get_the_terms(get_the_ID(), 'job_position');
        if ($job_positions && !is_wp_error($job_positions)) {
            $position = $job_positions[0];
            // カスタム形式のURLを生成
            $position_url = home_url('/jobs/position/' . $position->slug . '/');
            $breadcrumb .= ' &gt; <a href="' . esc_url($position_url) . '">' . esc_html($position->name) . '</a>';
        }
        
        // 求人タイトル
        $facility_name = get_post_meta(get_the_ID(), 'facility_name', true);
        if (!empty($facility_name)) {
            $breadcrumb .= ' &gt; ' . esc_html($facility_name);
        } else {
            $breadcrumb .= ' &gt; ' . get_the_title();
        }
    }
    
    // 検索キーワードがある場合は追加（どのページタイプでも）
    if (!empty($search_query)) {
        $breadcrumb .= ' &gt; <span>' . esc_html($search_query) . '</span><span style="font-size:0.8em;">(検索したワード)</span>';
    }
    
    // パンくずリストを閉じる
    $breadcrumb .= '</div>';
    
    return $breadcrumb;
}

/**
 * パンくずリストを表示する関数
 */
function display_breadcrumb() {
    echo improved_breadcrumb();
}

/**
 * ページタイトルを生成する関数
 */
function get_search_title() {
    // 検索キーワードを取得
    $search_query = get_search_query();
    if (!empty($search_query)) {
        return '「' . esc_html($search_query) . '」の検索結果';
    }
    
    // 条件を収集
    $conditions = array();
    
    // URLからパスパラメータを取得
    $current_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $parsed_url = parse_url($current_url);
    $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
    $query = isset($parsed_url['query']) ? $parsed_url['query'] : '';
    
    // クエリパラメータを解析
    $query_params = array();
    if (!empty($query)) {
        parse_str($query, $query_params);
    }
    
    // パスからtaxonomyパラメータを取得
    $path_parts = explode('/', trim($path, '/'));
    $jobs_index = array_search('jobs', $path_parts);
    
    if ($jobs_index !== false) {
        $taxonomy_map = array(
            'location' => 'job_location',
            'position' => 'job_position',
            'type' => 'job_type',
            'facility' => 'facility_type',
            'feature' => 'job_feature'
        );
        
        for ($i = $jobs_index + 1; $i < count($path_parts) - 1; $i += 2) {
            if (isset($path_parts[$i]) && isset($path_parts[$i+1])) {
                $tax_segment = $path_parts[$i];
                $term_slug = $path_parts[$i+1];
                
                if (isset($taxonomy_map[$tax_segment])) {
                    $taxonomy = $taxonomy_map[$tax_segment];
                    $term = get_term_by('slug', $term_slug, $taxonomy);
                    
                    if ($term) {
                        $conditions[] = $term->name;
                    }
                }
            }
        }
    }
    
    // クエリパラメータからfeature条件を取得
    if (isset($query_params['features']) && is_array($query_params['features'])) {
        foreach ($query_params['features'] as $slug) {
            $term = get_term_by('slug', $slug, 'job_feature');
            if ($term) {
                $conditions[] = $term->name;
            }
        }
    }
    
    // タクソノミーページの場合
    if (is_tax()) {
        $term = get_queried_object();
        if (!in_array($term->name, $conditions)) {
            $conditions[] = $term->name;
        }
    }
    
    // 条件がある場合は条件タイトルを返す
    if (!empty($conditions)) {
        return implode(' × ', $conditions) . 'の求人情報';
    }
    
    // デフォルト
    return '求人情報一覧';
}

/**
 * 求人詳細ページ用のパンくずリスト関数
 */
function job_detail_breadcrumb() {
    // 基本のパンくずリストを開始
    $breadcrumb = '<div class="breadcrumb">';
    $breadcrumb .= '<a href="' . home_url() . '">ホーム</a> &gt; ';
    
    // 求人詳細ページの場合
    if (is_singular('job')) {
        $post_id = get_the_ID();
        
        // 職種を取得
        $job_positions = get_the_terms($post_id, 'job_position');
        if ($job_positions && !is_wp_error($job_positions)) {
            $position = $job_positions[0];
            $position_url = home_url('/jobs/position/' . $position->slug . '/');
            $breadcrumb .= '<a href="' . esc_url($position_url) . '">' . esc_html($position->name) . '</a> &gt; ';
        }
        
        // エリア情報を階層的に表示（親→子→孫）
        $job_locations = get_the_terms($post_id, 'job_location');
        if ($job_locations && !is_wp_error($job_locations)) {
            // 最も詳細なターム（孫）を見つける
            $max_depth = -1;
            $most_specific_term = null;
            
            foreach ($job_locations as $location) {
                $ancestors = get_ancestors($location->term_id, 'job_location', 'taxonomy');
                $depth = count($ancestors);
                
                if ($depth > $max_depth) {
                    $most_specific_term = $location;
                    $max_depth = $depth;
                }
            }
            
            if ($most_specific_term) {
                // 祖先のタームを取得（親→祖父の順）
                $ancestors = array_reverse(get_ancestors($most_specific_term->term_id, 'job_location', 'taxonomy'));
                
                // 階層順に表示（親→子→孫）
                foreach ($ancestors as $ancestor_id) {
                    $ancestor = get_term($ancestor_id, 'job_location');
                    if (!is_wp_error($ancestor)) {
                        $ancestor_url = home_url('/jobs/location/' . $ancestor->slug . '/');
                        $breadcrumb .= '<a href="' . esc_url($ancestor_url) . '">' . esc_html($ancestor->name) . '</a> &gt; ';
                    }
                }
                
                // 最後に最も詳細なターム（孫）を表示
                $location_url = home_url('/jobs/location/' . $most_specific_term->slug . '/');
                $breadcrumb .= '<a href="' . esc_url($location_url) . '">' . esc_html($most_specific_term->name) . '</a> &gt; ';
            }
        }
        
        // 施設名を表示
        $facility_name = get_post_meta($post_id, 'facility_name', true);
        if (!empty($facility_name)) {
            $breadcrumb .= esc_html($facility_name);
        } else {
            $breadcrumb .= get_the_title();
        }
    } 
    // アーカイブページや検索ページの場合
    else {
        // 求人一覧ページの場合
        if (is_post_type_archive('job')) {
            $breadcrumb .= '求人情報一覧';
        }
        // タクソノミーページの場合
        else if (is_tax()) {
            $term = get_queried_object();
            $taxonomy = $term->taxonomy;
            
            // タクソノミー名からセグメント部分を決定
            $tax_segment = '';
            switch ($taxonomy) {
                case 'job_location':
                    $tax_segment = '地域';
                    break;
                case 'job_position':
                    $tax_segment = '職種';
                    break;
                case 'job_type':
                    $tax_segment = '雇用形態';
                    break;
                case 'facility_type':
                    $tax_segment = '施設タイプ';
                    break;
                case 'job_feature':
                    $tax_segment = '特徴';
                    break;
            }
            
            $breadcrumb .= '<a href="' . home_url('/jobs/') . '">求人情報一覧</a> &gt; ';
            $breadcrumb .= $tax_segment . ' &gt; ';
            
            // 階層を持つタクソノミーの場合は親も表示
            if ($term->parent != 0) {
                $parents = array();
                $parent_id = $term->parent;
                
                while ($parent_id) {
                    $parent = get_term($parent_id, $taxonomy);
                    if (is_wp_error($parent)) {
                        break;
                    }
                    $parents[] = $parent;
                    $parent_id = $parent->parent;
                }
                
                // 親タームを逆順で表示（祖父→親の順）
                foreach (array_reverse($parents) as $parent) {
                    $parent_url = home_url('/jobs/' . $tax_segment . '/' . $parent->slug . '/');
                    $breadcrumb .= '<a href="' . esc_url($parent_url) . '">' . esc_html($parent->name) . '</a> &gt; ';
                }
            }
            
            // 現在のタームを表示
            $breadcrumb .= $term->name;
        }
        // 検索結果ページの場合
        else if (is_search()) {
            $search_query = get_search_query();
            $breadcrumb .= '<a href="' . home_url('/jobs/') . '">求人情報一覧</a> &gt; ';
            $breadcrumb .= '「' . esc_html($search_query) . '」の検索結果';
        }
    }
    
    // パンくずリストを閉じる
    $breadcrumb .= '</div>';
    
    return $breadcrumb;
}

/**
 * パンくずリストを表示する関数
 */
function display_job_breadcrumb() {
    echo job_detail_breadcrumb();
}



/**
 * Contact Form 7でログインユーザー情報を自動表示する機能 (最も確実な方法)
 * functions.phpに追加してください
 */

// フォーム表示前に直接JavaScriptで値を設定
function auto_fill_cf7_with_js() {
    // ユーザーがログインしていない場合は何もしない
    if (!is_user_logged_in()) {
        return;
    }
    
    // 現在のユーザー情報を取得
    $user = wp_get_current_user();
    $user_name = esc_js($user->display_name);
    $user_email = esc_js($user->user_email);
    
    // 画面読み込み時にJavaScriptでフォームに値を設定
    ?>
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        // Contact Form 7の読み込み完了イベントを監視
        if (typeof wpcf7 !== 'undefined') {
            document.addEventListener('wpcf7:renderform', function() {
                console.log('CF7フォームがレンダリングされました');
                fillFormFields();
            });
        }
        
        // フォームに値を設定する関数
        function fillFormFields() {
            // すべてのフォームを取得
            const forms = document.querySelectorAll('.wpcf7-form');
            
            forms.forEach(function(form) {
                // 学校名フィールドを設定
                const schoolNameField = form.querySelector('input[name="school-name"]');
                if (schoolNameField && !schoolNameField.value) {
                    schoolNameField.value = "<?php echo $user_name; ?>";
                    console.log('教室名を設定: <?php echo $user_name; ?>');
                }
                
                // メールフィールドを設定
                const emailField = form.querySelector('input[name="user-email"]');
                if (emailField && !emailField.value) {
                    emailField.value = "<?php echo $user_email; ?>";
                    console.log('メールアドレスを設定: <?php echo $user_email; ?>');
                }
            });
        }
        
        // 最初の実行（ページ読み込み時）
        setTimeout(fillFormFields, 500);
    });
    </script>
    <?php
}
add_action('wp_footer', 'auto_fill_cf7_with_js');

// デバッグのためにユーザー情報をログに出力
function debug_cf7_user_info() {
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        error_log('CF7 Debug: ユーザーはログイン中です');
        error_log('CF7 Debug: ユーザー名 = ' . $user->display_name);
        error_log('CF7 Debug: メールアドレス = ' . $user->user_email);
    } else {
        error_log('CF7 Debug: ユーザーは未ログインです');
    }
}
add_action('wp_footer', 'debug_cf7_user_info');



/**
 * 求人カード全体クリックで詳細ページに遷移する機能
 */
function add_job_card_click_functionality() {
    // インラインJavaScriptのみを追加
    ?>
    <script>
    jQuery(document).ready(function($) {
        // ジョブカードのクリックイベントを設定
        $('.job-card').each(function() {
            // カード内の詳細ボタンのURLを取得
            var detailUrl = $(this).find('.detail-view-button').attr('href');
            
            if (detailUrl) {
                // カード自体をクリック可能にする
                $(this).css('cursor', 'pointer');
                
                // カードクリック時の処理
                $(this).on('click', function(e) {
                    // ボタンやリンク、フォーム要素などをクリックした場合はそれらの動作を優先
                    if ($(e.target).is('a, button, input, textarea, select, .keep-button, .keep-button *, .detail-view-button, .detail-view-button *, span.star, .star *')) {
                        return; // カード全体のクリックイベントをキャンセル
                    }
                    
                    // それ以外の部分をクリックした場合は詳細ページへ遷移
                    window.location.href = detailUrl;
                });
            }
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'add_job_card_click_functionality');

/**
 * agency ロールにファイルアップロード機能を追加
 */
function add_upload_capability_to_agency() {
    // agency ロールを取得
    $role = get_role('agency');
    
    // ロールが存在する場合のみ処理
    if ($role) {
        // 基本的なメディア操作権限を追加
        $role->add_cap('upload_files', true);
        $role->add_cap('edit_posts', true);
        $role->add_cap('publish_posts', true);
    }
}
add_action('init', 'add_upload_capability_to_agency', 999);

/**
 * メディアアップローダーのスクリプトを強制読み込み
 */
function force_media_for_agency_pages() {
    // ユーザーがログインしており、カスタム投稿ページにいる場合
    if (is_user_logged_in() && (is_page_template('page-post-job.php') || is_page_template('page-edit-job.php'))) {
        wp_enqueue_media();
    }
}
add_action('wp_enqueue_scripts', 'force_media_for_agency_pages', 20);

/**
 * メディア関連の処理で権限チェックをバイパス
 */
function allow_agency_media_access($allcaps, $caps, $args, $user) {
    // ユーザーが agency ロールを持っている場合
    if (isset($user->roles) && in_array('agency', (array) $user->roles)) {
        // メディア関連の権限を許可
        if (isset($caps[0]) && in_array($caps[0], array('upload_files', 'edit_posts'))) {
            $allcaps['upload_files'] = true;
            $allcaps['edit_posts'] = true;
        }
    }
    return $allcaps;
}
add_filter('user_has_cap', 'allow_agency_media_access', 10, 4);

/**
 * agency ユーザー用のメディア関連権限を強化
 */
function enhance_agency_media_capabilities() {
    // agency ロールを取得
    $role = get_role('agency');
    
    if ($role) {
        // メディア操作に必要な基本権限を追加
        $role->add_cap('upload_files', true);
        $role->add_cap('edit_posts', true);
        $role->add_cap('delete_posts', true);
    }
}
add_action('init', 'enhance_agency_media_capabilities', 1);

/**
 * wp-admin/async-upload.php などのメディア関連ページへのアクセスを許可
 */
function allow_media_access_for_agency() {
    if (!is_admin()) return;

    // 現在のユーザーがagencyロールを持っているか確認
    $user = wp_get_current_user();
    if (in_array('agency', (array) $user->roles)) {
        // 現在のページがメディア関連かチェック
        $page = isset($_SERVER['PHP_SELF']) ? basename($_SERVER['PHP_SELF']) : '';
        $allowed_pages = array(
            'async-upload.php',
            'media-upload.php',
            'upload.php',
            'admin-ajax.php'
        );
        
        // メディア関連のページへのアクセスを許可
        if (in_array($page, $allowed_pages)) {
            return;
        }
        
        // ajaxリクエストの場合も許可
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        // 管理画面プロフィール編集も許可
        global $pagenow;
        if ($pagenow == 'profile.php') {
            return;
        }
        
        // それ以外の管理画面ページは通常通りリダイレクト
        wp_redirect(home_url('/job-list/'));
        exit;
    }
}

// 既存の制限関数を削除して新しい関数に置き換え
remove_action('admin_init', 'restrict_admin_access');
add_action('admin_init', 'allow_media_access_for_agency', 1);

/**
 * メディアアップローダーのスクリプトを強制的に読み込む
 */
function enqueue_media_fix_scripts() {
    // 必要なページでのみ読み込む
    if (is_page_template('page-post-job.php') || is_page_template('page-edit-job.php')) {
        // メディアアップローダーのJSを強制読み込み
        wp_enqueue_media();
        
        // カスタムJSを読み込む
        wp_enqueue_script(
            'custom-media-fix',
            get_stylesheet_directory_uri() . '/js/media-fix.js',
            array('jquery', 'media-editor', 'jquery-ui-sortable'),
            '1.0.1',
            true
        );
        
        // AJAXのURLを渡す
        wp_localize_script('custom-media-fix', 'media_vars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'home_url' => home_url(),
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_media_fix_scripts', 999);

/**
 * メディアアップロード権限を加盟教室ユーザーに付与
 */
function add_upload_capability_for_agency() {
    // agency ロールに権限を付与
    $role = get_role('agency');
    if ($role) {
        $role->add_cap('upload_files', true);
    }
}
add_action('init', 'add_upload_capability_for_agency');

/**
 * 各ユーザーが自分のアップロードした画像のみ表示（非管理者向け）
 */
function filter_media_for_current_user($query) {
    // 管理者は全ての画像を表示
    if (current_user_can('administrator')) {
        return $query;
    }
    
    // 現在のユーザーを取得
    $user_id = get_current_user_id();
    
    // メディアクエリの場合はユーザーIDでフィルタリング
    if (isset($query['post_type']) && $query['post_type'] === 'attachment') {
        $query['author'] = $user_id;
    }
    
    return $query;
}
add_filter('ajax_query_attachments_args', 'filter_media_for_current_user');

/**
 * メディア関連ページへのアクセスを許可しつつ、他の管理画面はリダイレクト
 */
function allow_media_for_agency() {
    // 管理画面でない場合や管理者の場合はスキップ
    if (!is_admin() || current_user_can('administrator')) {
        return;
    }
    
    // 現在のユーザーがagencyロールを持つか確認
    $user = wp_get_current_user();
    if (!in_array('agency', (array)$user->roles)) {
        return;
    }

    // AJAXリクエストは許可
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    
    // 現在のページ
    $page = isset($_SERVER['PHP_SELF']) ? basename($_SERVER['PHP_SELF']) : '';
    
    // メディア関連ページリスト
    $allowed_pages = array(
        'async-upload.php',
        'upload.php',
        'media-upload.php',
        'admin-ajax.php',
        'profile.php'
    );
    
    // メディア関連ページ以外はリダイレクト
    if (!in_array($page, $allowed_pages)) {
        wp_redirect(home_url('/job-list/'));
        exit;
    }
}

// 既存の制限関数を削除
remove_action('admin_init', 'restrict_admin_access');
// 新しい制限関数を追加
add_action('admin_init', 'allow_media_for_agency', 1);

/**
 * メディアJS読み込み用の簡易関数
 */
function load_media_js_for_job_pages() {
    if (is_page_template('page-post-job.php') || is_page_template('page-edit-job.php')) {
        wp_enqueue_media();
    }
}
add_action('wp_enqueue_scripts', 'load_media_js_for_job_pages', 20);

/**
 * フロントエンド用の求人ステータス変更・削除処理
 */

// アクションフックの登録
add_action('wp_ajax_frontend_draft_job', 'frontend_set_job_to_draft');
add_action('wp_ajax_frontend_publish_job', 'frontend_set_job_to_publish');
add_action('wp_ajax_frontend_delete_job', 'frontend_delete_job');

/**
 * 求人を下書きに変更（フロントエンド用）
 */
function frontend_set_job_to_draft() {
    if (!isset($_POST['job_id']) || !isset($_POST['nonce'])) {
        wp_send_json_error('無効なリクエストです。');
    }
    
    $job_id = intval($_POST['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_POST['nonce'], 'frontend_job_action')) {
        wp_send_json_error('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック
    $job_post = get_post($job_id);
    if (!$job_post || $job_post->post_type !== 'job') {
        wp_send_json_error('求人が見つかりません。');
    }
    
    $current_user_id = get_current_user_id();
    if ($job_post->post_author != $current_user_id && !current_user_can('administrator')) {
        wp_send_json_error('この求人を編集する権限がありません。');
    }
    
    // 下書きに変更
    $result = wp_update_post(array(
        'ID' => $job_id,
        'post_status' => 'draft'
    ));
    
    if ($result) {
        wp_send_json_success(array(
            'message' => '求人を下書きに変更しました。',
            'redirect' => home_url('/job-list/?status=drafted')
        ));
    } else {
        wp_send_json_error('求人の更新に失敗しました。');
    }
}

/**
 * 求人を公開に変更（フロントエンド用）
 */
function frontend_set_job_to_publish() {
    if (!isset($_POST['job_id']) || !isset($_POST['nonce'])) {
        wp_send_json_error('無効なリクエストです。');
    }
    
    $job_id = intval($_POST['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_POST['nonce'], 'frontend_job_action')) {
        wp_send_json_error('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック
    $job_post = get_post($job_id);
    if (!$job_post || $job_post->post_type !== 'job') {
        wp_send_json_error('求人が見つかりません。');
    }
    
    $current_user_id = get_current_user_id();
    if ($job_post->post_author != $current_user_id && !current_user_can('administrator')) {
        wp_send_json_error('この求人を編集する権限がありません。');
    }
    
    // 公開に変更
    $result = wp_update_post(array(
        'ID' => $job_id,
        'post_status' => 'publish'
    ));
    
    if ($result) {
        wp_send_json_success(array(
            'message' => '求人を公開しました。',
            'redirect' => home_url('/job-list/?status=published')
        ));
    } else {
        wp_send_json_error('求人の更新に失敗しました。');
    }
}

/**
 * 求人を削除（フロントエンド用）
 */
function frontend_delete_job() {
    if (!isset($_POST['job_id']) || !isset($_POST['nonce'])) {
        wp_send_json_error('無効なリクエストです。');
    }
    
    $job_id = intval($_POST['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_POST['nonce'], 'frontend_job_action')) {
        wp_send_json_error('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック
    $job_post = get_post($job_id);
    if (!$job_post || $job_post->post_type !== 'job') {
        wp_send_json_error('求人が見つかりません。');
    }
    
    $current_user_id = get_current_user_id();
    if ($job_post->post_author != $current_user_id && !current_user_can('administrator')) {
        wp_send_json_error('この求人を削除する権限がありません。');
    }
    
    // 削除
    $result = wp_trash_post($job_id);
    
    if ($result) {
        wp_send_json_success(array(
            'message' => '求人を削除しました。',
            'redirect' => home_url('/job-list/?status=deleted')
        ));
    } else {
        wp_send_json_error('求人の削除に失敗しました。');
    }
}

// 単一タクソノミーのページネーション対応
// エリアのページネーション
add_rewrite_rule(
    'jobs/location/([^/]+)/page/([0-9]+)/?$',
    'index.php?post_type=job&job_location=$matches[1]&paged=$matches[2]',
    'top'
);

// 職種のページネーション
add_rewrite_rule(
    'jobs/position/([^/]+)/page/([0-9]+)/?$',
    'index.php?post_type=job&job_position=$matches[1]&paged=$matches[2]',
    'top'
);

// 雇用形態のページネーション
add_rewrite_rule(
    'jobs/type/([^/]+)/page/([0-9]+)/?$',
    'index.php?post_type=job&job_type=$matches[1]&paged=$matches[2]',
    'top'
);

// 施設形態のページネーション
add_rewrite_rule(
    'jobs/facility/([^/]+)/page/([0-9]+)/?$',
    'index.php?post_type=job&facility_type=$matches[1]&paged=$matches[2]',
    'top'
);

// 特徴のページネーション
add_rewrite_rule(
    'jobs/feature/([^/]+)/page/([0-9]+)/?$',
    'index.php?post_type=job&job_feature=$matches[1]&paged=$matches[2]',
    'top'
);
// 基本的な求人一覧ページのページネーション
add_rewrite_rule(
    'jobs/page/([0-9]+)/?$',
    'index.php?post_type=job&paged=$matches[1]',
    'top'
);


/**
 * 求人投稿（job）管理画面の一覧に施設名列を追加
 */

// 管理画面の投稿一覧に施設名の列を追加
function add_job_admin_columns($columns) {
    // タイトル列の後に施設名列を挿入
    $new_columns = array();
    
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        // タイトル列の直後に施設名列を追加
        if ($key === 'title') {
            $new_columns['facility_name'] = '施設名';
        }
    }
    
    return $new_columns;
}
add_filter('manage_job_posts_columns', 'add_job_admin_columns');

// 施設名列の内容を表示
function display_job_admin_column_content($column, $post_id) {
    switch ($column) {
        case 'facility_name':
            $facility_name = get_post_meta($post_id, 'facility_name', true);
            
            if (!empty($facility_name)) {
                echo '<strong>' . esc_html($facility_name) . '</strong>';
                
                // 運営会社名も表示（あれば）
                $facility_company = get_post_meta($post_id, 'facility_company', true);
                if (!empty($facility_company)) {
                    echo '<br><span style="color: #666; font-size: 0.9em;">' . esc_html($facility_company) . '</span>';
                }
            } else {
                echo '<span style="color: #999;">未設定</span>';
            }
            break;
    }
}
add_action('manage_job_posts_custom_column', 'display_job_admin_column_content', 10, 2);

// 施設名列をソート可能にする
function make_job_facility_name_sortable($columns) {
    $columns['facility_name'] = 'facility_name';
    return $columns;
}
add_filter('manage_edit-job_sortable_columns', 'make_job_facility_name_sortable');

// 施設名でのソート処理
function job_facility_name_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    $orderby = $query->get('orderby');
    
    if ('facility_name' === $orderby) {
        $query->set('meta_key', 'facility_name');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'job_facility_name_orderby');

// 管理画面のスタイル調整
function job_admin_column_styles() {
    global $current_screen;
    
    if ($current_screen && $current_screen->post_type === 'job' && $current_screen->base === 'edit') {
        ?>
        <style>
        .wp-list-table .column-facility_name {
            width: 20%;
        }
        .wp-list-table .column-title {
            width: 25%;
        }
        .wp-list-table .column-date {
            width: 10%;
        }
        .wp-list-table .column-author {
            width: 15%;
        }
        </style>
        <?php
    }
}
add_action('admin_head', 'job_admin_column_styles');

// フィルター機能: 施設名で検索できるようにする
function job_admin_search_custom_fields($search, $wp_query) {
    global $wpdb;
    
    if (empty($search) || !is_admin()) {
        return $search;
    }
    
    // 現在のスクリーンが求人投稿の管理画面かチェック
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'job') {
        return $search;
    }
    
    $search_term = $wp_query->query_vars['s'];
    if (!empty($search_term)) {
        $search .= " OR (";
        $search .= "(pm.meta_key = 'facility_name' AND pm.meta_value LIKE '%" . esc_sql($wpdb->esc_like($search_term)) . "%')";
        $search .= " OR (pm.meta_key = 'facility_company' AND pm.meta_value LIKE '%" . esc_sql($wpdb->esc_like($search_term)) . "%')";
        $search .= ")";
    }
    
    return $search;
}
add_filter('posts_search', 'job_admin_search_custom_fields', 10, 2);

// 検索時にメタテーブルをJOINする
function job_admin_search_join($join) {
    global $wpdb;
    
    if (is_admin() && is_search()) {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'job') {
            $join .= " LEFT JOIN $wpdb->postmeta pm ON $wpdb->posts.ID = pm.post_id ";
        }
    }
    
    return $join;
}
add_filter('posts_join', 'job_admin_search_join');

// 重複を避けるためにDISTINCTを追加
function job_admin_search_distinct($distinct) {
    if (is_admin() && is_search()) {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'job') {
            return "DISTINCT";
        }
    }
    
    return $distinct;
}
add_filter('posts_distinct', 'job_admin_search_distinct');

/**
 * 管理画面一覧での表示項目を増やす（オプション）
 */
function enhance_job_admin_columns($columns) {
    // より詳細な情報を表示したい場合は以下をアンコメント
    /*
    $new_columns = array();
    
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        if ($key === 'facility_name') {
            $new_columns['job_location'] = 'エリア';
            $new_columns['job_position'] = '職種';
            $new_columns['salary_range'] = '給与';
        }
    }
    
    return $new_columns;
    */
    
    return $columns;
}
// add_filter('manage_job_posts_columns', 'enhance_job_admin_columns', 20);

/**
 * 追加カラムの内容表示（オプション）
 */
function display_enhanced_job_columns($column, $post_id) {
    switch ($column) {
        case 'job_location':
            $locations = get_the_terms($post_id, 'job_location');
            if ($locations && !is_wp_error($locations)) {
                $location_names = array();
                foreach ($locations as $location) {
                    $location_names[] = $location->name;
                }
                echo esc_html(implode(', ', $location_names));
            } else {
                echo '<span style="color: #999;">未設定</span>';
            }
            break;
            
        case 'job_position':
            $positions = get_the_terms($post_id, 'job_position');
            if ($positions && !is_wp_error($positions)) {
                echo esc_html($positions[0]->name);
            } else {
                echo '<span style="color: #999;">未設定</span>';
            }
            break;
            
        case 'salary_range':
            $salary = get_post_meta($post_id, 'salary_range', true);
            if (!empty($salary)) {
                echo esc_html($salary);
            } else {
                echo '<span style="color: #999;">未設定</span>';
            }
            break;
    }
}
// add_action('manage_job_posts_custom_column', 'display_enhanced_job_columns', 10, 2);




/**
 * CSVファイルから既知のパスワードを使用してAgency ロールのユーザーにログイン情報を送信する関数
 */
function send_login_info_to_agency_users_with_known_passwords($csv_file_path) {
    // CSVファイルを読み込み
    if (!file_exists($csv_file_path)) {
        return array(
            'success' => false,
            'message' => 'CSVファイルが見つかりません: ' . $csv_file_path
        );
    }
    
    $csv_data = array();
    if (($handle = fopen($csv_file_path, "r")) !== FALSE) {
        $header = fgetcsv($handle); // ヘッダー行を取得
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row = array_combine($header, $data);
            $csv_data[] = $row;
        }
        fclose($handle);
    }
    
    if (empty($csv_data)) {
        return array(
            'success' => false,
            'message' => 'CSVファイルにデータがありません。'
        );
    }
    
    // agency ロールのユーザーのみをフィルタリング
    $agency_users = array_filter($csv_data, function($user) {
        return isset($user['role']) && stripos($user['role'], 'agency') !== false;
    });
    
    if (empty($agency_users)) {
        return array(
            'success' => false,
            'message' => 'Agency ロールのユーザーが見つかりませんでした。'
        );
    }
    
    $sent_count = 0;
    $failed_users = array();
    
    foreach ($agency_users as $user) {
        // 必要なフィールドが存在するかチェック
        if (empty($user['user_email']) || empty($user['password'])) {
            $failed_users[] = array(
                'email' => $user['user_email'] ?? 'N/A',
                'name' => $user['display_name'] ?? 'N/A',
                'reason' => 'メールアドレスまたはパスワードが不正'
            );
            continue;
        }
        
        // メール送信
        $result = send_agency_login_email_with_password(
            $user['user_email'],
            $user['display_name'] ?? $user['user_login'],
            $user['password']
        );
        
        if ($result) {
            $sent_count++;
            
            // ログに記録
            error_log("ログイン情報送信完了: {$user['user_email']} ({$user['display_name']})");
        } else {
            $failed_users[] = array(
                'email' => $user['user_email'],
                'name' => $user['display_name'] ?? $user['user_login'],
                'reason' => 'メール送信失敗'
            );
            
            error_log("ログイン情報送信失敗: {$user['user_email']} ({$user['display_name']})");
        }
    }
    
    return array(
        'success' => true,
        'total_users' => count($agency_users),
        'sent_count' => $sent_count,
        'failed_count' => count($failed_users),
        'failed_users' => $failed_users,
        'agency_users' => $agency_users
    );
}

/**
 * 既知のパスワードでAgency ユーザーにログイン情報メールを送信
 */
function send_agency_login_email_with_password($email, $display_name, $password) {
    $site_name = get_bloginfo('name');
    $login_url = 'https://recruitment.kodomo-plus.co.jp/instructor-login/';
    
    // メールの件名
    $subject = "[{$site_name}] ログイン情報のお知らせ";
    
    // メール本文
    $message = "
{$display_name} 様

ようこそ！このサイトにログインするためのデータは次のとおりです。

* ログイン用 URL: {$login_url}
* 登録メールアドレス: {$email}
* パスワード: {$password}

【重要】
- 上記のパスワードでログインしてください。
- このメールは大切に保管してください。
- ログインに関してご不明な点がございましたら、お問い合わせください。

よろしくお願いいたします。

---
{$site_name}
" . get_option('admin_email');
    
    // HTMLメール用のヘッダー
    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $site_name . ' <' . get_option('admin_email') . '>'
    );
    
    // メール送信
    return wp_mail($email, $subject, $message, $headers);
}

/**
 * アップロードされたCSVファイルを処理する関数
 */
function process_uploaded_csv_for_agency_login() {
    // ファイルアップロードの処理
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        return array(
            'success' => false,
            'message' => 'CSVファイルのアップロードに失敗しました。'
        );
    }
    
    $uploaded_file = $_FILES['csv_file'];
    $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
    
    if (strtolower($file_extension) !== 'csv') {
        return array(
            'success' => false,
            'message' => 'CSVファイルのみアップロード可能です。'
        );
    }
    
    // 一時的にファイルを保存
    $upload_dir = wp_upload_dir();
    $temp_file = $upload_dir['path'] . '/temp_agency_users_' . time() . '.csv';
    
    if (!move_uploaded_file($uploaded_file['tmp_name'], $temp_file)) {
        return array(
            'success' => false,
            'message' => 'ファイルの保存に失敗しました。'
        );
    }
    
    // CSVを処理
    $result = send_login_info_to_agency_users_with_known_passwords($temp_file);
    
    // 一時ファイルを削除
    unlink($temp_file);
    
    return $result;
}

/**
 * 管理画面から実行するための関数
 */
function execute_agency_login_sender_with_csv() {
    // 管理者権限チェック
    if (!current_user_can('manage_options')) {
        wp_die('権限がありません。');
    }
    
    // nonce チェック
    if (!wp_verify_nonce($_POST['_wpnonce'], 'send_agency_login_info_csv')) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    $result = process_uploaded_csv_for_agency_login();
    
    if ($result['success']) {
        $message = "ログイン情報の送信が完了しました。\n";
        $message .= "対象ユーザー数: {$result['total_users']}\n";
        $message .= "送信成功: {$result['sent_count']}\n";
        $message .= "送信失敗: {$result['failed_count']}\n";
        
        if (!empty($result['failed_users'])) {
            $message .= "\n送信失敗ユーザー:\n";
            foreach ($result['failed_users'] as $failed_user) {
                $message .= "- {$failed_user['name']} ({$failed_user['email']}) - 理由: {$failed_user['reason']}\n";
            }
        }
        
        wp_admin_notice($message, 'success');
    } else {
        wp_admin_notice($result['message'], 'error');
    }
    
    return $result;
}

/**
 * 管理画面メニューに追加
 */
function add_agency_login_sender_csv_menu() {
    add_management_page(
        'Agency ログイン情報送信 (CSV)',
        'Agency ログイン情報送信 (CSV)',
        'manage_options',
        'agency-login-sender-csv',
        'agency_login_sender_csv_page'
    );
}
add_action('admin_menu', 'add_agency_login_sender_csv_menu');

/**
 * 管理画面ページの表示
 */
function agency_login_sender_csv_page() {
    $result = null;
    
    // POST処理
    if (isset($_POST['send_login_info_csv'])) {
        $result = execute_agency_login_sender_with_csv();
    }
    
    ?>
    <div class="wrap">
        <h1>Agency ユーザーへのログイン情報送信 (CSV使用)</h1>
        
        <div class="notice notice-info">
            <p><strong>CSVファイル形式:</strong></p>
            <p>以下のカラムが必要です: <code>user_login, user_email, display_name, password, role</code></p>
            <p><strong>注意:</strong> role に "agency" が含まれるユーザーのみが対象になります。</p>
        </div>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('send_agency_login_info_csv'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="csv_file">CSVファイル</label></th>
                    <td>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required />
                        <p class="description">
                            ユーザー情報が含まれたCSVファイルを選択してください。<br>
                            形式: user_login, user_email, display_name, password, role
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="send_login_info_csv" class="button-primary" value="CSVを読み込んでログイン情報を送信" />
            </p>
        </form>
        
        <?php if ($result && $result['success'] && !empty($result['agency_users'])): ?>
            <h2>送信されたAgencyユーザー一覧</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>表示名</th>
                        <th>メールアドレス</th>
                        <th>ログイン名</th>
                        <th>役割</th>
                        <th>送信状況</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['agency_users'] as $user): ?>
                        <?php 
                        $failed = array_filter($result['failed_users'], function($failed_user) use ($user) {
                            return $failed_user['email'] === $user['user_email'];
                        });
                        $status = empty($failed) ? '✅ 成功' : '❌ 失敗';
                        ?>
                        <tr>
                            <td><?php echo esc_html($user['display_name']); ?></td>
                            <td><?php echo esc_html($user['user_email']); ?></td>
                            <td><?php echo esc_html($user['user_login']); ?></td>
                            <td><?php echo esc_html($user['role']); ?></td>
                            <td><?php echo $status; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
       
    </div>
    <?php
}

/**
 * WP-CLI コマンドとして実行する場合
 */
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('agency-login-csv', function($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error('CSVファイルのパスを指定してください。');
            return;
        }
        
        $csv_file = $args[0];
        WP_CLI::line("CSVファイル '{$csv_file}' からAgency ユーザーへのログイン情報送信を開始します...");
        
        $result = send_login_info_to_agency_users_with_known_passwords($csv_file);
        
        if ($result['success']) {
            WP_CLI::success("送信完了: {$result['sent_count']}/{$result['total_users']}");
            
            if (!empty($result['failed_users'])) {
                WP_CLI::warning('送信失敗ユーザー:');
                foreach ($result['failed_users'] as $failed_user) {
                    WP_CLI::line("- {$failed_user['name']} ({$failed_user['email']}) - {$failed_user['reason']}");
                }
            }
        } else {
            WP_CLI::error($result['message']);
        }
    });
}


/**
 * 職種の表示用テキストを取得
 */
function get_job_position_display_text($job_id) {
    $job_positions = wp_get_object_terms($job_id, 'job_position');
    
    if (!empty($job_positions) && !is_wp_error($job_positions)) {
        $position = $job_positions[0]; // ラジオボタンなので最初の1つ
        
        if ($position->slug === 'other') {
            $custom_position = get_post_meta($job_id, 'custom_job_position', true);
            return !empty($custom_position) ? $custom_position : $position->name;
        } else {
            return $position->name;
        }
    }
    
    return '';
}

/**
 * 雇用形態の表示用テキストを取得
 */
function get_job_type_display_text($job_id) {
    $job_types = wp_get_object_terms($job_id, 'job_type');
    
    if (!empty($job_types) && !is_wp_error($job_types)) {
        $type = $job_types[0]; // ラジオボタンなので最初の1つ
        
        if ($type->slug === 'others') {
            $custom_type = get_post_meta($job_id, 'custom_job_type', true);
            return !empty($custom_type) ? $custom_type : $type->name;
        } else {
            return $type->name;
        }
    }
    
    return '';
}

/**
 * 共通情報の更新処理に職員の声を追加
 * 既存の共通情報更新処理に以下を追加してください
 */
function update_common_staff_voices($target_user_id) {
    // 職員の声（配列形式）の処理を追加
    if (isset($_POST['staff_voice_role']) && is_array($_POST['staff_voice_role'])) {
        $voice_items = array();
        $count = count($_POST['staff_voice_role']);
        
        for ($i = 0; $i < $count; $i++) {
            if (!empty($_POST['staff_voice_role'][$i])) { // 職種が入力されている項目のみ保存
                $voice_items[] = array(
                    'image_id' => intval($_POST['staff_voice_image'][$i]),
                    'role' => sanitize_text_field($_POST['staff_voice_role'][$i]),
                    'years' => sanitize_text_field($_POST['staff_voice_years'][$i]),
                    'comment' => wp_kses_post($_POST['staff_voice_comment'][$i])
                );
            }
        }
        
        // 共通職員の声をユーザーメタに保存
        update_user_meta($target_user_id, 'common_staff_voice_items', $voice_items);
        
        // 対象ユーザーの全求人投稿に職員の声を適用
        $user_jobs = get_posts(array(
            'post_type' => 'job',
            'posts_per_page' => -1,
            'author' => $target_user_id,
            'post_status' => array('publish', 'draft', 'pending')
        ));
        
        if (!empty($user_jobs)) {
            foreach ($user_jobs as $job) {
                update_post_meta($job->ID, 'staff_voice_items', $voice_items);
            }
        }
    }
}
// functions.php に追加するコード
function ensure_staff_voice_sync_on_job_save($post_id) {
    // 求人投稿タイプでない場合は何もしない
    if (get_post_type($post_id) !== 'job') {
        return;
    }
    
    // 自動保存の場合は何もしない
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // 投稿者IDを取得
    $post_author_id = get_post_field('post_author', $post_id);
    if (empty($post_author_id)) {
        return;
    }
    
    // 共通職員の声を取得
    $common_staff_voice = get_user_meta($post_author_id, 'common_staff_voice_items', true);
    
    // 共通情報がある場合は、この求人投稿にも適用
    if (!empty($common_staff_voice) && is_array($common_staff_voice)) {
        update_post_meta($post_id, 'staff_voice_items', $common_staff_voice);
    }
}
// add_action('save_post_job', 'ensure_staff_voice_sync_on_job_save', 25);

function get_user_redirect_url() {
    return is_user_logged_in() ? '/members/' : '/register/';
}

add_shortcode('user_redirect_url', 'get_user_redirect_url');




/**
 * 管理画面の求人投稿（job）に投稿者選択ドロップダウンを追加
 * functions.phpに追加してください
 */

/**
 * 求人投稿の投稿者選択ドロップダウンを管理画面に追加
 */
function add_job_author_meta_box() {
    add_meta_box(
        'job_author_selection',
        '投稿者選択',
        'render_job_author_meta_box',
        'job',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'add_job_author_meta_box');

/**
 * 投稿者選択メタボックスのレンダリング
 */
function render_job_author_meta_box($post) {
    // nonce フィールドを作成
    wp_nonce_field('save_job_author', 'job_author_nonce');
    
    // 現在の投稿者を取得
    $current_author = $post->post_author;
    
    // agencyロールのユーザーを取得
    $agency_users = get_users(array(
        'role' => 'agency',
        'orderby' => 'display_name',
        'order' => 'ASC'
    ));
    
    // 管理者も選択肢に含める
    $admin_users = get_users(array(
        'role' => 'administrator',
        'orderby' => 'display_name', 
        'order' => 'ASC'
    ));
    
    // 全ユーザーを結合
    $all_users = array_merge($agency_users, $admin_users);
    
    ?>
    <div class="job-author-selection">
        <p>
            <label for="job_post_author"><strong>投稿者を選択:</strong></label>
        </p>
        <p>
            <select name="job_post_author" id="job_post_author" style="width: 100%;">
                <?php if (empty($all_users)): ?>
                    <option value="<?php echo get_current_user_id(); ?>">ユーザーが見つかりません</option>
                <?php else: ?>
                    <?php foreach ($all_users as $user): ?>
                        <option value="<?php echo $user->ID; ?>" <?php selected($current_author, $user->ID); ?>>
                            <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)
                            <?php if (in_array('administrator', $user->roles)): ?>
                                - 管理者
                            <?php elseif (in_array('agency', $user->roles)): ?>
                                - 加盟教室
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </p>
        <p class="description">
            選択したユーザーの求人一覧ページに表示されます。<br>
            agencyユーザーの場合、そのユーザーの共通情報も自動適用されます。
        </p>
    </div>
    
    <style>
    .job-author-selection {
        padding: 10px 0;
    }
    
    .job-author-selection label {
        font-weight: 600;
        margin-bottom: 5px;
        display: block;
    }
    
    .job-author-selection select {
        padding: 5px;
        border: 1px solid #ddd;
        border-radius: 3px;
    }
    
    .job-author-selection .description {
        font-size: 12px;
        color: #666;
        margin-top: 8px;
        margin-bottom: 0;
    }
    </style>
    <?php
}

/**
 * 投稿者選択の保存処理
 */
function save_job_author_selection($post_id) {
    // 自動保存の場合は何もしない
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // nonceチェック
    if (!isset($_POST['job_author_nonce']) || !wp_verify_nonce($_POST['job_author_nonce'], 'save_job_author')) {
        return;
    }
    
    // 権限チェック
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // 投稿者IDが送信されている場合
    if (isset($_POST['job_post_author']) && !empty($_POST['job_post_author'])) {
        $new_author_id = intval($_POST['job_post_author']);
        
        // 選択されたユーザーが存在し、適切な権限を持っているか確認
        $new_author = get_userdata($new_author_id);
        if ($new_author && (
            in_array('agency', (array)$new_author->roles) || 
            in_array('administrator', (array)$new_author->roles)
        )) {
            // 投稿者を更新
            wp_update_post(array(
                'ID' => $post_id,
                'post_author' => $new_author_id
            ));
            
            // agencyユーザーの場合は共通情報を適用
            if (in_array('agency', (array)$new_author->roles)) {
                apply_common_info_to_job($post_id, $new_author_id);
            }
        }
    }
}
add_action('save_post_job', 'save_job_author_selection');

/**
 * 共通情報を求人投稿に適用する関数
 */
function apply_common_info_to_job($job_id, $user_id) {
    // ユーザーメタから共通情報を取得
    $common_location_slugs = get_user_meta($user_id, 'common_job_location_slugs', true);
    $common_facility_info = get_user_meta($user_id, 'common_facility_info', true);
    $common_facility_type = get_user_meta($user_id, 'common_facility_type', true);
    $common_full_address = get_user_meta($user_id, 'common_full_address', true);
    $common_staff_voice = get_user_meta($user_id, 'common_staff_voice_items', true);
    
    // 勤務地域の適用
    if (!empty($common_location_slugs) && is_array($common_location_slugs)) {
        wp_set_object_terms($job_id, $common_location_slugs, 'job_location');
    }
    
    // 施設形態の適用
    if (!empty($common_facility_type) && is_array($common_facility_type)) {
        wp_set_object_terms($job_id, $common_facility_type, 'facility_type');
    }
    
    // 事業所情報の適用
    if (!empty($common_facility_info) && is_array($common_facility_info)) {
        foreach ($common_facility_info as $key => $value) {
            update_post_meta($job_id, $key, $value);
        }
    }
    
    // 完全な住所の適用
    if (!empty($common_full_address)) {
        update_post_meta($job_id, 'facility_address', $common_full_address);
    }
    
    // 職員の声の適用
    if (!empty($common_staff_voice) && is_array($common_staff_voice)) {
        update_post_meta($job_id, 'staff_voice_items', $common_staff_voice);
    }
}

/**
 * 管理画面の求人一覧に投稿者情報をより詳しく表示
 */
function enhance_job_admin_author_column($columns) {
    // 既存の投稿者列を置き換え
    if (isset($columns['author'])) {
        $columns['author'] = '投稿者 (ロール)';
    }
    return $columns;
}
add_filter('manage_job_posts_columns', 'enhance_job_admin_author_column');

/**
 * 投稿者列に追加情報を表示
 */
function display_enhanced_job_author_info($column, $post_id) {
    if ($column === 'author') {
        $author_id = get_post_field('post_author', $post_id);
        $author = get_userdata($author_id);
        
        if ($author) {
            echo '<strong>' . esc_html($author->display_name) . '</strong><br>';
            echo '<small>' . esc_html($author->user_email) . '</small><br>';
            
            if (in_array('administrator', (array)$author->roles)) {
                echo '<span style="color: #d63638; font-weight: bold;">管理者</span>';
            } elseif (in_array('agency', (array)$author->roles)) {
                echo '<span style="color: #00a32a; font-weight: bold;">加盟教室</span>';
            } else {
                echo '<span style="color: #787c82;">その他</span>';
            }
        }
    }
}
add_action('manage_job_posts_custom_column', 'display_enhanced_job_author_info', 10, 2);

/**
 * 管理画面で投稿者による絞り込み機能を追加
 */
function add_job_author_filter() {
    global $typenow;
    
    if ($typenow == 'job') {
        // 現在選択されている投稿者
        $selected_author = isset($_GET['author']) ? $_GET['author'] : '';
        
        // agencyユーザーを取得
        $agency_users = get_users(array(
            'role' => 'agency',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));
        
        // 管理者も取得
        $admin_users = get_users(array(
            'role' => 'administrator',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));
        
        $all_users = array_merge($agency_users, $admin_users);
        
        if (!empty($all_users)) {
            echo '<select name="author">';
            echo '<option value="">全ての投稿者</option>';
            
            foreach ($all_users as $user) {
                $role_label = '';
                if (in_array('administrator', (array)$user->roles)) {
                    $role_label = ' (管理者)';
                } elseif (in_array('agency', (array)$user->roles)) {
                    $role_label = ' (加盟教室)';
                }
                
                printf(
                    '<option value="%s"%s>%s%s</option>',
                    $user->ID,
                    selected($selected_author, $user->ID, false),
                    esc_html($user->display_name),
                    esc_html($role_label)
                );
            }
            
            echo '</select>';
        }
    }
}
add_action('restrict_manage_posts', 'add_job_author_filter');

/**
 * 新規投稿時のデフォルト投稿者設定（管理画面用）
 */
function set_default_job_author_in_admin() {
    global $post, $typenow;
    
    // 管理画面の新規投稿画面でのみ実行
    if (is_admin() && $typenow == 'job' && (!$post || $post->post_status == 'auto-draft')) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // 最初のagencyユーザーをデフォルトで選択（管理者が作成する場合）
            var $authorSelect = $('#job_post_author');
            if ($authorSelect.length && !$authorSelect.val()) {
                // agencyロールのユーザーがいれば最初のユーザーを選択
                var $agencyOption = $authorSelect.find('option:contains("加盟教室")').first();
                if ($agencyOption.length) {
                    $authorSelect.val($agencyOption.val());
                }
            }
        });
        </script>
        <?php
    }
}
add_action('admin_footer', 'set_default_job_author_in_admin');

/**
 * 管理画面での求人作成時の説明メッセージを追加
 */
function add_job_creation_notice() {
    global $post, $typenow;
    
    if (is_admin() && $typenow == 'job' && (!$post || $post->post_status == 'auto-draft')) {
        ?>
        <div class="notice notice-info">
            <p><strong>求人投稿の注意事項：</strong></p>
            <ul>
                <li>投稿者を選択すると、その人の求人一覧ページに表示されます</li>
                <li>agencyユーザーを選択した場合、そのユーザーの共通情報（施設情報等）が自動的に適用されます</li>
                <li>管理者として投稿する場合は、必要な情報をすべて手動で入力してください</li>
            </ul>
        </div>
        <?php
    }
}
add_action('admin_notices', 'add_job_creation_notice');



/**
 * 求人の編集・削除権限を修正
 * functions.phpに追加してください
 */

/**
 * 既存の求人ステータス変更・削除処理を修正版に置き換え
 */

// 既存の関数を削除してから新しい関数を追加
remove_action('admin_post_draft_job', 'set_job_to_draft');
remove_action('admin_post_publish_job', 'set_job_to_publish');
remove_action('admin_post_delete_job', 'delete_job_post');

remove_action('wp_ajax_frontend_draft_job', 'frontend_set_job_to_draft');
remove_action('wp_ajax_frontend_publish_job', 'frontend_set_job_to_publish');
remove_action('wp_ajax_frontend_delete_job', 'frontend_delete_job');

/**
 * 求人の編集・削除権限をチェックする関数
 */
function can_user_edit_job($job_id, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    // ログインしていない場合は不可
    if (!$user_id) {
        return false;
    }
    
    // 管理者は常に可能
    if (user_can($user_id, 'administrator')) {
        return true;
    }
    
    $job_post = get_post($job_id);
    if (!$job_post || $job_post->post_type !== 'job') {
        return false;
    }
    
    // 投稿者本人は可能
    if ($job_post->post_author == $user_id) {
        return true;
    }
    
    // agencyロールで、かつedit_jobs権限を持っている場合も可能
    $user = get_userdata($user_id);
    if ($user && in_array('agency', (array)$user->roles) && user_can($user_id, 'edit_jobs')) {
        return true;
    }
    
    return false;
}

/**
 * 修正版：求人を下書きに変更（バックエンド用）
 */
function fixed_set_job_to_draft() {
    if (!isset($_GET['job_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('無効なリクエストです。');
    }
    
    $job_id = intval($_GET['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_GET['_wpnonce'], 'draft_job_' . $job_id)) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック（修正版）
    if (!can_user_edit_job($job_id)) {
        wp_die('この求人を編集する権限がありません。');
    }
    
    // 下書きに変更
    $result = wp_update_post(array(
        'ID' => $job_id,
        'post_status' => 'draft'
    ));
    
    if ($result) {
        wp_redirect(home_url('/job-list/?status=drafted'));
    } else {
        wp_die('求人の更新に失敗しました。');
    }
    exit;
}

/**
 * 修正版：求人を公開に変更（バックエンド用）
 */
function fixed_set_job_to_publish() {
    if (!isset($_GET['job_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('無効なリクエストです。');
    }
    
    $job_id = intval($_GET['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_GET['_wpnonce'], 'publish_job_' . $job_id)) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック（修正版）
    if (!can_user_edit_job($job_id)) {
        wp_die('この求人を編集する権限がありません。');
    }
    
    // 公開に変更
    $result = wp_update_post(array(
        'ID' => $job_id,
        'post_status' => 'publish'
    ));
    
    if ($result) {
        wp_redirect(home_url('/job-list/?status=published'));
    } else {
        wp_die('求人の更新に失敗しました。');
    }
    exit;
}

/**
 * 修正版：求人を削除（バックエンド用）
 */
function fixed_delete_job_post() {
    if (!isset($_GET['job_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('無効なリクエストです。');
    }
    
    $job_id = intval($_GET['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_job_' . $job_id)) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック（修正版）
    if (!can_user_edit_job($job_id)) {
        wp_die('この求人を削除する権限がありません。');
    }
    
    // 削除
    $result = wp_trash_post($job_id);
    
    if ($result) {
        wp_redirect(home_url('/job-list/?status=deleted'));
    } else {
        wp_die('求人の削除に失敗しました。');
    }
    exit;
}

/**
 * 修正版：フロントエンド用求人ステータス変更・削除処理
 */
function fixed_frontend_set_job_to_draft() {
    if (!isset($_POST['job_id']) || !isset($_POST['nonce'])) {
        wp_send_json_error('無効なリクエストです。');
    }
    
    $job_id = intval($_POST['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_POST['nonce'], 'frontend_job_action')) {
        wp_send_json_error('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック（修正版）
    if (!can_user_edit_job($job_id)) {
        wp_send_json_error('この求人を編集する権限がありません。');
    }
    
    // 下書きに変更
    $result = wp_update_post(array(
        'ID' => $job_id,
        'post_status' => 'draft'
    ));
    
    if ($result) {
        wp_send_json_success(array(
            'message' => '求人を下書きに変更しました。',
            'redirect' => home_url('/job-list/?status=drafted')
        ));
    } else {
        wp_send_json_error('求人の更新に失敗しました。');
    }
}

function fixed_frontend_set_job_to_publish() {
    if (!isset($_POST['job_id']) || !isset($_POST['nonce'])) {
        wp_send_json_error('無効なリクエストです。');
    }
    
    $job_id = intval($_POST['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_POST['nonce'], 'frontend_job_action')) {
        wp_send_json_error('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック（修正版）
    if (!can_user_edit_job($job_id)) {
        wp_send_json_error('この求人を編集する権限がありません。');
    }
    
    // 公開に変更
    $result = wp_update_post(array(
        'ID' => $job_id,
        'post_status' => 'publish'
    ));
    
    if ($result) {
        wp_send_json_success(array(
            'message' => '求人を公開しました。',
            'redirect' => home_url('/job-list/?status=published')
        ));
    } else {
        wp_send_json_error('求人の更新に失敗しました。');
    }
}

function fixed_frontend_delete_job() {
    if (!isset($_POST['job_id']) || !isset($_POST['nonce'])) {
        wp_send_json_error('無効なリクエストです。');
    }
    
    $job_id = intval($_POST['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_POST['nonce'], 'frontend_job_action')) {
        wp_send_json_error('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック（修正版）
    if (!can_user_edit_job($job_id)) {
        wp_send_json_error('この求人を削除する権限がありません。');
    }
    
    // 削除
    $result = wp_trash_post($job_id);
    
    if ($result) {
        wp_send_json_success(array(
            'message' => '求人を削除しました。',
            'redirect' => home_url('/job-list/?status=deleted')
        ));
    } else {
        wp_send_json_error('求人の削除に失敗しました。');
    }
}

// 修正版の関数をフックに追加
add_action('admin_post_draft_job', 'fixed_set_job_to_draft');
add_action('admin_post_publish_job', 'fixed_set_job_to_publish');
add_action('admin_post_delete_job', 'fixed_delete_job_post');

add_action('wp_ajax_frontend_draft_job', 'fixed_frontend_set_job_to_draft');
add_action('wp_ajax_frontend_publish_job', 'fixed_frontend_set_job_to_publish');
add_action('wp_ajax_frontend_delete_job', 'fixed_frontend_delete_job');

/**
 * page-edit-job.phpの権限チェックも修正するための関数
 */
function check_job_edit_permission($job_id) {
    if (!is_user_logged_in()) {
        return false;
    }
    
    return can_user_edit_job($job_id);
}

/**
 * agencyロールに必要な追加権限を確実に付与
 */
function ensure_agency_job_permissions() {
    $role = get_role('agency');
    
    if ($role) {
        // 求人関連の権限を追加
        $capabilities = array(
            'edit_jobs' => true,
            'edit_published_jobs' => true,
            'delete_jobs' => true,
            'delete_published_jobs' => true,
            'publish_jobs' => true,
            'read_private_jobs' => false,
            'edit_others_jobs' => false,
            'delete_others_jobs' => false,
            'edit_job' => true,
            'read_job' => true,
            'delete_job' => true,
        );
        
        foreach ($capabilities as $cap => $grant) {
            $role->add_cap($cap, $grant);
        }
    }
}
add_action('init', 'ensure_agency_job_permissions', 11);

/**
 * デバッグ用：ユーザーの権限をログに出力（必要に応じて有効化）
 */
function debug_user_job_permissions($job_id = null, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        error_log('Debug: User not logged in');
        return;
    }
    
    $user = get_userdata($user_id);
    error_log('Debug: User ID: ' . $user_id);
    error_log('Debug: User roles: ' . implode(', ', $user->roles));
    error_log('Debug: User capabilities: ' . print_r($user->allcaps, true));
    
    if ($job_id) {
        $job_post = get_post($job_id);
        error_log('Debug: Job ID: ' . $job_id);
        error_log('Debug: Job author: ' . $job_post->post_author);
        error_log('Debug: Can edit job: ' . (can_user_edit_job($job_id, $user_id) ? 'YES' : 'NO'));
    }
}

// デバッグを有効にする場合は下記のコメントアウトを解除
// add_action('wp_footer', function() {
//     if (is_page_template('page-job-list.php') && is_user_logged_in()) {
//         debug_user_job_permissions();
//     }
// });



/**
 * 求人複製機能
 * functions.phpに追加してください
 */

// === フロントエンド用求人複製処理 ===
add_action('wp_ajax_frontend_duplicate_job', 'frontend_duplicate_job');

/**
 * 求人を複製する（フロントエンド用）
 */
function frontend_duplicate_job() {
    if (!isset($_POST['job_id']) || !isset($_POST['nonce'])) {
        wp_send_json_error('無効なリクエストです。');
    }
    
    $job_id = intval($_POST['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_POST['nonce'], 'frontend_job_action')) {
        wp_send_json_error('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック（編集権限があるユーザーのみ複製可能）
    if (!can_user_edit_job($job_id)) {
        wp_send_json_error('この求人を複製する権限がありません。');
    }
    
    // 求人を複製
    $duplicated_job_id = duplicate_job_post($job_id);
    
    if ($duplicated_job_id && !is_wp_error($duplicated_job_id)) {
        wp_send_json_success(array(
            'message' => '求人を複製しました。',
            'redirect' => home_url('/edit-job/?job_id=' . $duplicated_job_id),
            'new_job_id' => $duplicated_job_id
        ));
    } else {
        $error_message = is_wp_error($duplicated_job_id) ? $duplicated_job_id->get_error_message() : '求人の複製に失敗しました。';
        wp_send_json_error($error_message);
    }
}

/**
 * 求人投稿を複製する関数
 *
 * @param int $job_id 複製元の求人ID
 * @return int|WP_Error 新しい求人のIDまたはエラー
 */
/**
 * 求人投稿を複製する関数（修正版）
 * functions.phpの該当部分を以下に置き換えてください
 */
function duplicate_job_post($job_id) {
    // 元の投稿を取得
    $original_post = get_post($job_id);
    
    if (!$original_post || $original_post->post_type !== 'job') {
        return new WP_Error('invalid_post', '無効な求人IDです。');
    }
    
    // 新しい投稿データを準備
    $new_post_data = array(
        'post_title'   => $original_post->post_title . '（コピー）',
        'post_content' => $original_post->post_content,
        'post_status'  => 'draft', // 複製は下書きとして作成
        'post_type'    => 'job',
        'post_author'  => get_current_user_id(),
        'post_excerpt' => $original_post->post_excerpt,
    );
    
    // 新しい投稿を作成
    $new_job_id = wp_insert_post($new_post_data);
    
    if (is_wp_error($new_job_id)) {
        return $new_job_id;
    }
    
    // カスタムフィールドをコピー
    $meta_fields = array(
        'job_content_title',
        'salary_range',
        'working_hours',
        'holidays',
        'benefits',
        'requirements',
        'application_process',
        'contact_info',
        'bonus_raise',
        'facility_name',
        'facility_company',
        'company_url',
        'facility_address',
        'facility_tel',
        'facility_hours',
        'facility_url',
        'facility_map',
        'facility_zipcode',
        'facility_address_detail',
        'capacity',
        'staff_composition',
        'salary_type',
        'salary_form',
        'salary_min',
        'salary_max',
        'fixed_salary',
        'salary_remarks',
        'custom_job_position',
        'custom_job_type',
        'daily_schedule_items',
        'staff_voice_items',
        'job_thumbnail_ids'
    );
    
    foreach ($meta_fields as $meta_key) {
        $meta_value = get_post_meta($job_id, $meta_key, true);
        if (!empty($meta_value)) {
            update_post_meta($new_job_id, $meta_key, $meta_value);
        }
    }
    
    // タクソノミーをコピー（修正版）
    $taxonomies = array('job_location', 'job_position', 'job_type', 'facility_type', 'job_feature');
    
    foreach ($taxonomies as $taxonomy) {
        // タームをスラッグで取得してコピー
        $terms = wp_get_object_terms($job_id, $taxonomy, array('fields' => 'slugs'));
        
        if (!empty($terms) && !is_wp_error($terms)) {
            // デバッグログ出力（必要に応じて）
            error_log("複製処理: {$taxonomy} のターム数: " . count($terms) . " - タームリスト: " . implode(', ', $terms));
            
            // タクソノミーを設定
            $result = wp_set_object_terms($new_job_id, $terms, $taxonomy);
            
            if (is_wp_error($result)) {
                error_log("複製処理エラー: {$taxonomy} の設定に失敗 - " . $result->get_error_message());
            } else {
                error_log("複製処理成功: {$taxonomy} が正常に設定されました");
            }
        } else {
            // 元の投稿にタームが設定されていない場合の処理
            wp_set_object_terms($new_job_id, array(), $taxonomy);
            error_log("複製処理: {$taxonomy} にはタームが設定されていません");
        }
    }
    
    // サムネイル画像をコピー
    $thumbnail_id = get_post_thumbnail_id($job_id);
    if ($thumbnail_id) {
        set_post_thumbnail($new_job_id, $thumbnail_id);
    }
    
    // 複数サムネイル画像をコピー
    $thumbnail_ids = get_post_meta($job_id, 'job_thumbnail_ids', true);
    if (!empty($thumbnail_ids) && is_array($thumbnail_ids)) {
        update_post_meta($new_job_id, 'job_thumbnail_ids', $thumbnail_ids);
    }
    
    // 複製完了後の追加処理（ユーザーの共通情報を適用）
    $current_user_id = get_current_user_id();
    
    // ユーザーメタから共通情報を取得して適用
    $common_location_slugs = get_user_meta($current_user_id, 'common_job_location_slugs', true);
    $common_facility_info = get_user_meta($current_user_id, 'common_facility_info', true);
    $common_facility_type = get_user_meta($current_user_id, 'common_facility_type', true);
    $common_full_address = get_user_meta($current_user_id, 'common_full_address', true);
    $common_staff_voice = get_user_meta($current_user_id, 'common_staff_voice_items', true);
    
    // 勤務地域の適用（共通情報が優先）
    if (!empty($common_location_slugs) && is_array($common_location_slugs)) {
        wp_set_object_terms($new_job_id, $common_location_slugs, 'job_location');
        error_log("複製処理: 共通勤務地域を適用しました");
    }
    
    // 施設形態の適用（共通情報が優先）
    if (!empty($common_facility_type) && is_array($common_facility_type)) {
        $facility_result = wp_set_object_terms($new_job_id, $common_facility_type, 'facility_type');
        if (is_wp_error($facility_result)) {
            error_log("複製処理エラー: 共通施設形態の適用に失敗 - " . $facility_result->get_error_message());
        } else {
            error_log("複製処理成功: 共通施設形態を適用しました - " . implode(', ', $common_facility_type));
        }
    }
    
    // 事業所情報の適用（共通情報が優先）
    if (!empty($common_facility_info) && is_array($common_facility_info)) {
        foreach ($common_facility_info as $key => $value) {
            if (!empty($value)) {
                update_post_meta($new_job_id, $key, $value);
            }
        }
        error_log("複製処理: 共通事業所情報を適用しました");
    }
    
    // 完全な住所の適用（共通情報が優先）
    if (!empty($common_full_address)) {
        update_post_meta($new_job_id, 'facility_address', $common_full_address);
        error_log("複製処理: 共通住所情報を適用しました");
    }
    
    // 職員の声の適用（共通情報が優先）
    if (!empty($common_staff_voice) && is_array($common_staff_voice)) {
        update_post_meta($new_job_id, 'staff_voice_items', $common_staff_voice);
        error_log("複製処理: 共通職員の声を適用しました");
    }
    
    return $new_job_id;
}

/**
 * デバッグ用：複製後の施設形態確認関数
 * 複製がうまくいかない場合のデバッグに使用
 */
function debug_duplicated_job_facility_type($job_id) {
    $facility_types = wp_get_object_terms($job_id, 'facility_type');
    
    error_log("デバッグ: 求人ID {$job_id} の施設形態:");
    if (empty($facility_types) || is_wp_error($facility_types)) {
        error_log("  - 施設形態が設定されていません");
    } else {
        foreach ($facility_types as $term) {
            error_log("  - ターム: {$term->name} (スラッグ: {$term->slug}, ID: {$term->term_id})");
        }
    }
    
    // ユーザーの共通設定も確認
    $current_user_id = get_current_user_id();
    $common_facility_type = get_user_meta($current_user_id, 'common_facility_type', true);
    
    error_log("ユーザーID {$current_user_id} の共通施設形態:");
    if (empty($common_facility_type)) {
        error_log("  - 共通施設形態が設定されていません");
    } else {
        error_log("  - 共通設定: " . print_r($common_facility_type, true));
    }
}

// === バックエンド用求人複製処理（管理画面用） ===
add_action('admin_post_duplicate_job', 'backend_duplicate_job');

/**
 * 求人を複製する（バックエンド用）
 */
function backend_duplicate_job() {
    if (!isset($_GET['job_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('無効なリクエストです。');
    }
    
    $job_id = intval($_GET['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_GET['_wpnonce'], 'duplicate_job_' . $job_id)) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック
    if (!can_user_edit_job($job_id)) {
        wp_die('この求人を複製する権限がありません。');
    }
    
    // 求人を複製
    $new_job_id = duplicate_job_post($job_id);
    
    if ($new_job_id && !is_wp_error($new_job_id)) {
        // 複製成功：編集ページへリダイレクト
        wp_redirect(home_url('/edit-job/?job_id=' . $new_job_id . '&duplicated=true'));
    } else {
        // 複製失敗：エラーメッセージとともに元のページへ戻る
        wp_redirect(home_url('/job-list/?error=duplicate_failed'));
    }
    exit;
}

/**
 * 求人一覧ページで複製ボタンのnonceを生成するヘルパー関数
 */
function get_duplicate_job_nonce($job_id) {
    return wp_create_nonce('duplicate_job_' . $job_id);
}

/**
 * 複製ボタンのURLを生成するヘルパー関数
 */
function get_duplicate_job_url($job_id) {
    return admin_url('admin-post.php?action=duplicate_job&job_id=' . $job_id . '&_wpnonce=' . get_duplicate_job_nonce($job_id));
}




// functions.phpに追加するコード

/**
 * タクソノミー管理画面にACFフィールドのカラムを追加
 */
function add_acf_columns_to_taxonomy($columns) {
    // 既存のカラムの後にACFフィールドのカラムを追加
    $new_columns = array();
    
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        // 'name'カラムの後にACFフィールドを挿入
        if ($key === 'name') {
            $new_columns['acf_claas'] = '教室名';
            $new_columns['acf_addressa'] = '住所';
            $new_columns['acf_tella'] = '電話番号';
            $new_columns['acf_web_urla'] = 'WEBサイトURL';
        }
    }
    
    return $new_columns;
}
add_filter('manage_edit-classname_columns', 'add_acf_columns_to_taxonomy');

/**
 * ACFフィールドの値を表示
 */
function show_acf_column_content($content, $column_name, $term_id) {
    switch ($column_name) {
        case 'acf_claas':
            $field_value = get_field('claas', 'classname_' . $term_id);
            return !empty($field_value) ? esc_html($field_value) : '未設定';
            
        case 'acf_addressa':
            $field_value = get_field('addressa', 'classname_' . $term_id);
            return !empty($field_value) ? esc_html($field_value) : '未設定';
            
        case 'acf_tella':
            $field_value = get_field('tella', 'classname_' . $term_id);
            return !empty($field_value) ? esc_html($field_value) : '未設定';
            
        case 'acf_web_urla':
            $field_value = get_field('web-urla', 'classname_' . $term_id);
            if (!empty($field_value)) {
                // URLの場合はリンクとして表示
                return '<a href="' . esc_url($field_value) . '" target="_blank">' . esc_html($field_value) . '</a>';
            }
            return '未設定';
    }
    
    return $content;
}
add_filter('manage_classname_custom_column', 'show_acf_column_content', 10, 3);

/**
 * カラムのソート機能を追加（オプション）
 */
function make_acf_columns_sortable($sortable) {
    $sortable['acf_claas'] = 'acf_claas';
    $sortable['acf_addressa'] = 'acf_addressa';
    $sortable['acf_tella'] = 'acf_tella';
    $sortable['acf_web_urla'] = 'acf_web_urla';
    return $sortable;
}
add_filter('manage_edit-classname_sortable_columns', 'make_acf_columns_sortable');

/**
 * ソート処理の実装（オプション）
 */
function sort_acf_columns($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    $orderby = $query->get('orderby');
    
    if ('acf_claas' === $orderby) {
        $query->set('meta_key', 'claas');
        $query->set('orderby', 'meta_value');
    }
    
    if ('acf_addressa' === $orderby) {
        $query->set('meta_key', 'addressa');
        $query->set('orderby', 'meta_value');
    }
    
    if ('acf_tella' === $orderby) {
        $query->set('meta_key', 'tella');
        $query->set('orderby', 'meta_value');
    }
    
    if ('acf_web_urla' === $orderby) {
        $query->set('meta_key', 'web-urla');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'sort_acf_columns');

/**
 * より高度な例：複数のACFフィールドを動的に追加
 */
function add_dynamic_acf_columns($columns) {
    // ACFフィールドグループから自動的にフィールドを取得
    $field_group = acf_get_field_groups(array(
        'taxonomy' => 'classname'
    ));
    
    if ($field_group) {
        $fields = acf_get_fields($field_group[0]['key']);
        
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            if ($key === 'name') {
                foreach ($fields as $field) {
                    // 特定のフィールドタイプのみ表示
                    if (in_array($field['type'], ['text', 'textarea', 'select', 'number'])) {
                        $new_columns['acf_' . $field['name']] = $field['label'];
                    }
                }
            }
        }
        
        return $new_columns;
    }
    
    return $columns;
}
// 動的版を使用する場合は上記の関数をコメントアウトしてこちらを有効化
// add_filter('manage_edit-classname_columns', 'add_dynamic_acf_columns');

/**
 * 動的版のコンテンツ表示
 */
function show_dynamic_acf_content($content, $column_name, $term_id) {
    if (strpos($column_name, 'acf_') === 0) {
        $field_name = str_replace('acf_', '', $column_name);
        $field_value = get_field($field_name, 'classname_' . $term_id);
        
        if (!empty($field_value)) {
            // フィールドタイプに応じて表示を調整
            if (is_array($field_value)) {
                return esc_html(implode(', ', $field_value));
            } else {
                return esc_html($field_value);
            }
        }
        
        return '未設定';
    }
    
    return $content;
}
// add_filter('manage_classname_custom_column', 'show_dynamic_acf_content', 10, 3);

/**
 * カラムの幅を調整（オプション）
 */
function acf_taxonomy_admin_css() {
    echo '<style>
        .wp-list-table .column-acf_claas { width: 150px; }
        .wp-list-table .column-acf_addressa { width: 200px; }
        .wp-list-table .column-acf_tella { width: 120px; }
        .wp-list-table .column-acf_web_urla { width: 200px; }
        .wp-list-table .column-acf_web_urla a { 
            color: #0073aa; 
            text-decoration: none; 
        }
        .wp-list-table .column-acf_web_urla a:hover { 
            text-decoration: underline; 
        }
    </style>';
}
add_action('admin_head', 'acf_taxonomy_admin_css');



// functions.phpに追加するコード

/**
 * 管理画面にCSVインポートページを追加
 */
function add_csv_import_menu() {
    // メインメニューに追加する方法
    add_management_page(
        'ACFフィールドCSVインポート',
        '教室CSVインポート',
        'manage_categories',
        'classname-csv-import',
        'display_csv_import_page'
    );
    
    // または、より直接的にサブメニューを追加
    global $submenu;
    $parent_slug = 'edit-tags.php?taxonomy=classname&post_type=job';
    add_submenu_page(
        null, // 親メニューをnullにして直接アクセス可能にする
        'ACFフィールドCSVインポート',
        'CSVインポート',
        'manage_categories',
        'classname-acf-csv-import',
        'display_csv_import_page'
    );
}
add_action('admin_menu', 'add_csv_import_menu');

/**
 * タクソノミー画面にCSVインポートリンクを追加
 */
function add_csv_import_link_to_taxonomy_page() {
    $screen = get_current_screen();
    
    if ($screen && $screen->id === 'edit-classname') {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // ページタイトルの後にボタンを追加
            var importButton = '<a href="<?php echo admin_url('tools.php?page=classname-csv-import'); ?>" class="page-title-action">CSVインポート</a>';
            $('.page-title-action').first().after(importButton);
            
            // または、検索ボックスの横に追加
            var importLink = '<div style="float: right; margin: 0 10px;"><a href="<?php echo admin_url('tools.php?page=classname-csv-import'); ?>" class="button button-secondary">CSVインポート</a></div>';
            $('.search-form').before(importLink);
        });
        </script>
        
        <style>
        .csv-import-notice {
            background: #e7f3ff;
            border-left: 4px solid #0073aa;
            padding: 10px 15px;
            margin: 20px 0;
        }
        </style>
        
        <div class="csv-import-notice">
            <p><strong>CSVインポート機能:</strong> 
            <a href="<?php echo admin_url('tools.php?page=classname-csv-import'); ?>">こちらから</a>CSVファイルを使用してACFフィールドを一括インポートできます。
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'add_csv_import_link_to_taxonomy_page');

/**
 * CSVインポートページの表示
 */
function display_csv_import_page() {
    // インポート処理
    if (isset($_POST['import_csv']) && isset($_FILES['csv_file'])) {
        handle_csv_import();
    }
    
    // CSVサンプルダウンロード処理
    if (isset($_GET['download_sample'])) {
        download_sample_csv();
        return;
    }
    
    ?>
    <div class="wrap">
        <h1>ACFフィールド CSVインポート</h1>
        
        <div class="notice notice-info">
            <p><strong>使用方法:</strong></p>
            <ul>
                <li>名前またはスラッグで既存のタームを識別してACFフィールドを更新します</li>
                <li>存在しないタームは新規作成されます</li>
                <li>CSVの1行目はヘッダー行として扱われます</li>
            </ul>
        </div>
        
        <!-- サンプルCSVダウンロード -->
        <div class="card">
            <h2>1. サンプルCSVファイルをダウンロード</h2>
            <p>正しいフォーマットのCSVファイルをダウンロードできます。</p>
            <a href="<?php echo admin_url('tools.php?page=classname-csv-import&download_sample=1'); ?>" class="button button-secondary">サンプルCSVダウンロード</a>
        </div>
        
        <!-- 既存データのエクスポート -->
        <div class="card">
            <h2>2. 既存データをエクスポート（編集用）</h2>
            <p>現在のデータをCSV形式でエクスポートして編集に使用できます。</p>
            <button type="button" class="button button-secondary" onclick="exportExistingData()">既存データをエクスポート</button>
        </div>
        
        <!-- CSVインポート -->
        <div class="card">
            <h2>3. CSVファイルをインポート</h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('csv_import_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">CSVファイル</th>
                        <td>
                            <input type="file" name="csv_file" accept=".csv" required />
                            <p class="description">UTF-8エンコードのCSVファイルを選択してください</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">識別方法</th>
                        <td>
                            <label><input type="radio" name="match_by" value="name" checked /> 名前で一致</label><br />
                            <label><input type="radio" name="match_by" value="slug" /> スラッグで一致</label><br />
                            <label><input type="radio" name="match_by" value="both" /> 名前とスラッグ両方試行</label>
                            <p class="description">既存タームとの一致方法を選択</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">新規作成</th>
                        <td>
                            <label>
                                <input type="checkbox" name="create_new" value="1" checked />
                                一致しないタームを新規作成する
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">上書きモード</th>
                        <td>
                            <label><input type="radio" name="overwrite_mode" value="empty_only" checked /> 空のフィールドのみ更新</label><br />
                            <label><input type="radio" name="overwrite_mode" value="all" /> すべてのフィールドを上書き</label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="import_csv" class="button button-primary" value="CSVをインポート" />
                </p>
            </form>
        </div>
        
        <!-- プレビュー機能 -->
        <div class="card">
            <h2>4. インポートプレビュー</h2>
            <p>インポート前にデータを確認できます。</p>
            <input type="file" id="preview_file" accept=".csv" />
            <button type="button" class="button" onclick="previewCSV()">プレビュー表示</button>
            <div id="preview_result" style="margin-top: 20px;"></div>
        </div>
    </div>
    
    <style>
    .card {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-left: 4px solid #0073aa;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        margin-bottom: 20px;
        padding: 20px;
    }
    .card h2 {
        margin-top: 0;
    }
    #preview_result table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    #preview_result th,
    #preview_result td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }
    #preview_result th {
        background-color: #f1f1f1;
    }
    .import-log {
        background: #f9f9f9;
        border: 1px solid #ddd;
        padding: 15px;
        margin: 20px 0;
        max-height: 400px;
        overflow-y: auto;
    }
    </style>
    
    <script>
    function exportExistingData() {
        window.location.href = '<?php echo admin_url('admin-ajax.php?action=export_taxonomy_csv&nonce=' . wp_create_nonce('export_csv')); ?>';
    }
    
    function previewCSV() {
        const fileInput = document.getElementById('preview_file');
        const file = fileInput.files[0];
        
        if (!file) {
            alert('CSVファイルを選択してください');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const csv = e.target.result;
            const lines = csv.split('\n');
            const headers = lines[0].split(',');
            
            let table = '<table><thead><tr>';
            headers.forEach(header => {
                table += `<th>${header.trim()}</th>`;
            });
            table += '</tr></thead><tbody>';
            
            // 最初の5行だけ表示
            for (let i = 1; i < Math.min(6, lines.length); i++) {
                if (lines[i].trim()) {
                    const cells = lines[i].split(',');
                    table += '<tr>';
                    cells.forEach(cell => {
                        table += `<td>${cell.trim()}</td>`;
                    });
                    table += '</tr>';
                }
            }
            
            table += '</tbody></table>';
            
            if (lines.length > 6) {
                table += `<p>... 他 ${lines.length - 6} 行</p>`;
            }
            
            document.getElementById('preview_result').innerHTML = table;
        };
        
        reader.readAsText(file, 'UTF-8');
    }
    </script>
    <?php
}

/**
 * サンプルCSVファイルのダウンロード
 */
function download_sample_csv() {
    $filename = 'sample_classname_import.csv';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    
    // BOM を追加（Excelで正しく表示されるため）
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // ヘッダー行
    fputcsv($output, array('name', 'slug', 'description', 'claas', 'addressa', 'tella', 'web-urla'));
    
    // サンプルデータ
    fputcsv($output, array('サンプル教室1', 'sample-class-1', '教室の説明', 'サンプル教室名1', '東京都渋谷区1-1-1', '03-1234-5678', 'https://example1.com'));
    fputcsv($output, array('サンプル教室2', 'sample-class-2', '教室の説明', 'サンプル教室名2', '東京都新宿区2-2-2', '03-2345-6789', 'https://example2.com'));
    fputcsv($output, array('サンプル教室3', 'sample-class-3', '教室の説明', 'サンプル教室名3', '東京都池袋区3-3-3', '03-3456-7890', 'https://example3.com'));
    
    fclose($output);
    exit;
}

/**
 * 既存データのエクスポート
 */
function ajax_export_taxonomy_csv() {
    // nonceチェック
    if (!wp_verify_nonce($_GET['nonce'], 'export_csv')) {
        wp_die('Security check failed');
    }
    
    // 権限チェック
    if (!current_user_can('manage_categories')) {
        wp_die('Permission denied');
    }
    
    $filename = 'classname_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    
    // BOM を追加
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // ヘッダー行
    fputcsv($output, array('name', 'slug', 'description', 'claas', 'addressa', 'tella', 'web-urla'));
    
    // 既存のターム一覧を取得
    $terms = get_terms(array(
        'taxonomy' => 'classname',
        'hide_empty' => false,
    ));
    
    foreach ($terms as $term) {
        $data = array(
            $term->name,
            $term->slug,
            $term->description,
            get_field('claas', 'classname_' . $term->term_id) ?: '',
            get_field('addressa', 'classname_' . $term->term_id) ?: '',
            get_field('tella', 'classname_' . $term->term_id) ?: '',
            get_field('web-urla', 'classname_' . $term->term_id) ?: ''
        );
        
        fputcsv($output, $data);
    }
    
    fclose($output);
    exit;
}
add_action('wp_ajax_export_taxonomy_csv', 'ajax_export_taxonomy_csv');

/**
 * CSVインポート処理
 */
function handle_csv_import() {
    // nonceチェック
    if (!wp_verify_nonce($_POST['_wpnonce'], 'csv_import_nonce')) {
        echo '<div class="notice notice-error"><p>セキュリティチェックに失敗しました。</p></div>';
        return;
    }
    
    // 権限チェック
    if (!current_user_can('manage_categories')) {
        echo '<div class="notice notice-error"><p>権限がありません。</p></div>';
        return;
    }
    
    // ファイルアップロードチェック
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo '<div class="notice notice-error"><p>ファイルのアップロードに失敗しました。</p></div>';
        return;
    }
    
    $csv_file = $_FILES['csv_file']['tmp_name'];
    $match_by = sanitize_text_field($_POST['match_by']);
    $create_new = isset($_POST['create_new']);
    $overwrite_mode = sanitize_text_field($_POST['overwrite_mode']);
    
    // CSVファイルを読み込み
    $file = fopen($csv_file, 'r');
    if (!$file) {
        echo '<div class="notice notice-error"><p>CSVファイルを読み込めませんでした。</p></div>';
        return;
    }
    
    $imported = 0;
    $updated = 0;
    $created = 0;
    $errors = array();
    $log = array();
    
    // ヘッダー行を読み取り
    $headers = fgetcsv($file);
    if (!$headers) {
        echo '<div class="notice notice-error"><p>CSVファイルが空か、形式が正しくありません。</p></div>';
        fclose($file);
        return;
    }
    
    // 必要なカラムの位置を特定
    $column_map = array();
    foreach ($headers as $index => $header) {
        $column_map[trim($header)] = $index;
    }
    
    // データ行を処理
    while (($data = fgetcsv($file)) !== false) {
        if (empty(array_filter($data))) {
            continue; // 空行をスキップ
        }
        
        try {
            $result = process_csv_row($data, $column_map, $match_by, $create_new, $overwrite_mode);
            
            if ($result['success']) {
                $imported++;
                if ($result['created']) {
                    $created++;
                } else {
                    $updated++;
                }
                $log[] = $result['message'];
            } else {
                $errors[] = $result['message'];
            }
            
        } catch (Exception $e) {
            $errors[] = '行の処理中にエラー: ' . $e->getMessage();
        }
    }
    
    fclose($file);
    
    // 結果表示
    echo '<div class="notice notice-success"><p>';
    echo "インポート完了: {$imported}件処理 (新規作成: {$created}件, 更新: {$updated}件)";
    echo '</p></div>';
    
    if (!empty($errors)) {
        echo '<div class="notice notice-warning"><p>エラー: ' . count($errors) . '件</p></div>';
    }
    
    // ログ表示
    if (!empty($log) || !empty($errors)) {
        echo '<div class="import-log">';
        echo '<h3>インポートログ</h3>';
        
        foreach ($log as $message) {
            echo '<p style="color: green;">✓ ' . esc_html($message) . '</p>';
        }
        
        foreach ($errors as $error) {
            echo '<p style="color: red;">✗ ' . esc_html($error) . '</p>';
        }
        
        echo '</div>';
    }
}

/**
 * CSV行の処理
 */
function process_csv_row($data, $column_map, $match_by, $create_new, $overwrite_mode) {
    $name = isset($column_map['name']) ? trim($data[$column_map['name']]) : '';
    $slug = isset($column_map['slug']) ? trim($data[$column_map['slug']]) : '';
    $description = isset($column_map['description']) ? trim($data[$column_map['description']]) : '';
    
    if (empty($name) && empty($slug)) {
        return array('success' => false, 'message' => '名前またはスラッグが必要です');
    }
    
    // 既存タームを検索
    $term = null;
    
    if ($match_by === 'name' && !empty($name)) {
        $term = get_term_by('name', $name, 'classname');
    } elseif ($match_by === 'slug' && !empty($slug)) {
        $term = get_term_by('slug', $slug, 'classname');
    } elseif ($match_by === 'both') {
        if (!empty($name)) {
            $term = get_term_by('name', $name, 'classname');
        }
        if (!$term && !empty($slug)) {
            $term = get_term_by('slug', $slug, 'classname');
        }
    }
    
    $created = false;
    
    // タームが存在しない場合は新規作成
    if (!$term && $create_new) {
        $term_data = wp_insert_term($name, 'classname', array(
            'slug' => $slug,
            'description' => $description
        ));
        
        if (is_wp_error($term_data)) {
            return array('success' => false, 'message' => 'タームの作成に失敗: ' . $term_data->get_error_message());
        }
        
        $term = get_term($term_data['term_id'], 'classname');
        $created = true;
    }
    
    if (!$term) {
        return array('success' => false, 'message' => "タームが見つかりません: {$name}");
    }
    
    // ACFフィールドを更新
    $acf_fields = array(
        'claas' => isset($column_map['claas']) ? trim($data[$column_map['claas']]) : '',
        'addressa' => isset($column_map['addressa']) ? trim($data[$column_map['addressa']]) : '',
        'tella' => isset($column_map['tella']) ? trim($data[$column_map['tella']]) : '',
        'web-urla' => isset($column_map['web-urla']) ? trim($data[$column_map['web-urla']]) : ''
    );
    
    foreach ($acf_fields as $field_key => $value) {
        if ($overwrite_mode === 'empty_only') {
            $current_value = get_field($field_key, 'classname_' . $term->term_id);
            if (!empty($current_value)) {
                continue; // 既存の値がある場合はスキップ
            }
        }
        
        if (!empty($value)) {
            update_field($field_key, $value, 'classname_' . $term->term_id);
        }
    }
    
    $action = $created ? '新規作成' : '更新';
    return array(
        'success' => true,
        'created' => $created,
        'message' => "{$action}: {$term->name}"
    );
}

/**
 * 管理画面のタクソノミー一覧にインポートボタンを追加
 */
function add_import_button_to_taxonomy_list() {
    $screen = get_current_screen();
    
    if ($screen && $screen->id === 'edit-classname') {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // ページタイトルの後にボタンを追加（既存のボタンがある場合）
            if ($('.page-title-action').length > 0) {
                $('.page-title-action').first().after('<a href="<?php echo admin_url('tools.php?page=classname-csv-import'); ?>" class="page-title-action">CSVインポート</a>');
            } else {
                // ページタイトルの後にボタンを追加（既存のボタンがない場合）
                $('h1.wp-heading-inline').after('<a href="<?php echo admin_url('tools.php?page=classname-csv-import'); ?>" class="page-title-action">CSVインポート</a>');
            }
        });
        </script>
        <?php
    }
}
add_action('admin_footer', 'add_import_button_to_taxonomy_list');


/**
 * WordPress シンプル メンテナンスモード（加盟教室対応版）
 * functions.phpに追加するコード
 */

// メンテナンスモードのON/OFF設定
define('MAINTENANCE_MODE', false); // true: メンテナンス中, false: 通常運用

/**
 * 加盟教室の権限を持つユーザーかカスタムフィールドでチェック
 * （既存のis_agency_user()関数があるため、補助関数として作成）
 */
function is_agency_user_by_meta() {
    $user_id = get_current_user_id();
    if (!$user_id) return false;
    
    // ユーザーのメタデータで加盟教室かどうかをチェック
    $user_type = get_user_meta($user_id, 'user_type', true);
    return $user_type === 'agency';
}

/**
 * メンテナンスモードの実行
 */
function simple_maintenance_mode() {
    // 管理者は通常通りサイトを閲覧可能
    if (current_user_can('administrator')) {
        return;
    }
    
    // 加盟教室も通常通りサイトを閲覧可能（既存の関数を使用）
    if (function_exists('is_agency_user') && is_agency_user()) {
        return;
    }
    
    // 念のため、メタデータでもチェック
    if (is_agency_user_by_meta()) {
        return;
    }
    
    // メンテナンスモードが有効な場合
    if (MAINTENANCE_MODE) {
        // 除外するページのチェック
        $current_url = $_SERVER['REQUEST_URI'];
        $excluded_pages = array(
            '/instructor-login/',
            '/wp-login.php',
            '/wp-admin/',
            '/login/',
        );
        
        // 除外ページの場合は通常表示
        foreach ($excluded_pages as $page) {
            if (strpos($current_url, $page) !== false) {
                return;
            }
        }
        
        // Ajax リクエストの場合は除外
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        // 503ステータスを返す（検索エンジン対策）
        http_response_code(503);
        
        // シンプルなメンテナンス画面
        echo '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メンテナンス中</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f1f1;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .maintenance {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
        }
        h1 { color: #333; margin-bottom: 20px; }
        p { color: #666; line-height: 1.5; }
        .login-link {
            margin-top: 20px;
        }
        .login-link a {
            color: #0073aa;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="maintenance">
        <h1>🔧 メンテナンス中</h1>
        <p>申し訳ございませんが、現在メンテナンス作業を行っております。</p>
        <p>しばらくお待ちください。</p>
        <div class="login-link">
            <a href="https://recruitment.kodomo-plus.co.jp/instructor-login/">加盟教室の方はこちらからログイン</a>
        </div>
    </div>
</body>
</html>';
        exit;
    }
}

// フロントエンドでメンテナンスモードをチェック
add_action('wp_loaded', 'simple_maintenance_mode');

/**
 * 管理者・加盟教室に通知（管理バー）
 */
function maintenance_admin_notice($wp_admin_bar) {
    if (MAINTENANCE_MODE && (current_user_can('administrator') || 
        (function_exists('is_agency_user') && is_agency_user()) || 
        is_agency_user_by_meta())) {
        $wp_admin_bar->add_node(array(
            'id'    => 'maintenance-notice',
            'title' => '⚠️ メンテナンス中',
            'meta'  => array('style' => 'background:#d63638; color:white;')
        ));
    }
}
add_action('admin_bar_menu', 'maintenance_admin_notice', 100);

/**
 * 加盟教室の権限を作成（初回のみ実行）
 */
function create_agency_role() {
    // 'agency'権限が存在しない場合のみ作成
    if (!get_role('agency')) {
        add_role('agency', '加盟教室', array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
        ));
    }
}
// テーマ有効化時に実行
add_action('after_setup_theme', 'create_agency_role');

/**
 * ユーザーに加盟教室のメタデータを設定する関数（管理者用）
 * 使用例: set_user_as_agency(123); // ユーザーID 123を加盟教室に設定
 */
function set_user_as_agency($user_id) {
    update_user_meta($user_id, 'user_type', 'agency');
}

/**
 * 加盟教室のメタデータを削除する関数（管理者用）
 * 使用例: remove_user_from_agency(123); // ユーザーID 123から加盟教室を削除
 */
function remove_user_from_agency($user_id) {
    delete_user_meta($user_id, 'user_type');
}



/**
 * 404エラー修正用のコード
 * 既存のfunctions.phpに追加してください
 */

/**
 * カスタム投稿タイプのページネーション404エラーを修正
 */
function fix_job_pagination_404($query) {
    // フロントエンドのメインクエリのみ対象
    if (!is_admin() && $query->is_main_query()) {
        
        // 求人投稿タイプの場合
        if (is_post_type_archive('job') || 
            is_tax('job_location') || 
            is_tax('job_position') || 
            is_tax('job_type') || 
            is_tax('facility_type') || 
            is_tax('job_feature')) {
            
            // デバッグ情報をログに出力
            $paged = get_query_var('paged') ? get_query_var('paged') : 1;
            error_log('Job pagination debug: paged=' . $paged . ', query_vars=' . print_r($query->query_vars, true));
            
            // 404エラーを無効にする
            $query->is_404 = false;
            status_header(200);
            
            // ページが1未満の場合は1に設定
            if ($paged < 1) {
                $query->set('paged', 1);
            }
        }
    }
}
add_action('pre_get_posts', 'fix_job_pagination_404', 1);

/**
 * template_redirectで404をさらに修正
 */
function prevent_job_archive_404() {
    global $wp_query;
    
    // 求人関連のページで404になっている場合
    if (is_404()) {
        $request_uri = $_SERVER['REQUEST_URI'];
        
        // 求人関連のURLパターンをチェック
        if (preg_match('/^\/jobs\//', $request_uri)) {
            error_log('Preventing 404 for job URL: ' . $request_uri);
            
            // 404フラグを解除
            $wp_query->is_404 = false;
            status_header(200);
            
            // 強制的にarchive-job.phpを読み込む
            if (locate_template('archive-job.php')) {
                include(locate_template('archive-job.php'));
                exit;
            }
        }
    }
}
add_action('template_redirect', 'prevent_job_archive_404', 1);

/**
 * wp_titleとbody_classを修正（404ページとして扱わない）
 */
function fix_job_archive_title($title) {
    global $wp_query;
    
    if (!is_admin() && 
        ($wp_query->get('post_type') === 'job' || 
         is_tax('job_location') || 
         is_tax('job_position') || 
         is_tax('job_type') || 
         is_tax('facility_type') || 
         is_tax('job_feature'))) {
        
        // 404フラグが立っていても強制的に解除
        $wp_query->is_404 = false;
    }
    
    return $title;
}
add_filter('wp_title', 'fix_job_archive_title');

/**
 * body_classから404クラスを削除
 */
function remove_404_body_class($classes) {
    global $wp_query;
    
    if ($wp_query->get('post_type') === 'job' || 
        is_tax('job_location') || 
        is_tax('job_position') || 
        is_tax('job_type') || 
        is_tax('facility_type') || 
        is_tax('job_feature')) {
        
        // 404クラスを削除
        $classes = array_diff($classes, array('error404'));
        
        // 適切なクラスを追加
        if (!in_array('post-type-archive-job', $classes)) {
            $classes[] = 'post-type-archive-job';
        }
    }
    
    return $classes;
}
add_filter('body_class', 'remove_404_body_class');

/**
 * 求人アーカイブページのクエリを最適化
 */
function optimize_job_archive_query($query) {
    if (!is_admin() && $query->is_main_query()) {
        
        // 求人投稿タイプのアーカイブページ
        if (is_post_type_archive('job') || 
            is_tax('job_location') || 
            is_tax('job_position') || 
            is_tax('job_type') || 
            is_tax('facility_type') || 
            is_tax('job_feature')) {
            
            // 投稿数を設定
            $query->set('posts_per_page', 10);
            
            // 公開済みの投稿のみ
            $query->set('post_status', 'publish');
            
            // meta_queryの最適化
            $query->set('meta_query', array());
            
            // orderbyの設定
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
            
            // 404フラグを確実に無効化
            $query->is_404 = false;
        }
    }
}
add_action('pre_get_posts', 'optimize_job_archive_query', 99);

/**
 * デバッグ用：現在のクエリ状態をログに出力
 */
function debug_job_query_status() {
    if (is_admin()) return;
    
    global $wp_query;
    
    // 求人関連ページのみ
    if ($wp_query->get('post_type') === 'job' || 
        is_tax('job_location') || 
        is_tax('job_position') || 
        is_tax('job_type') || 
        is_tax('facility_type') || 
        is_tax('job_feature')) {
        
        $debug_info = array(
            'is_404' => is_404(),
            'paged' => get_query_var('paged'),
            'found_posts' => $wp_query->found_posts,
            'max_num_pages' => $wp_query->max_num_pages,
            'post_count' => $wp_query->post_count,
            'request_uri' => $_SERVER['REQUEST_URI'],
            'query_vars' => $wp_query->query_vars
        );
        
        error_log('Job Query Debug: ' . print_r($debug_info, true));
    }
}
add_action('wp', 'debug_job_query_status');

/**
 * リライトルールを確実に適用
 */
function ensure_job_rewrite_rules() {
    global $wp_rewrite;
    
    // リライトルールが空の場合はフラッシュ
    if (empty($wp_rewrite->rules)) {
        flush_rewrite_rules();
    }
}
add_action('init', 'ensure_job_rewrite_rules', 999);

/**
 * 求人アーカイブのカスタムクエリ修正
 */
function custom_job_archive_query($query) {
    // 管理画面やメインクエリ以外は処理しない
    if (is_admin() || !$query->is_main_query()) {
        return;
    }
    
    // 求人アーカイブページかチェック
    if ($query->get('post_type') === 'job') {
        
        // ページネーション情報を取得
        $paged = get_query_var('paged') ? get_query_var('paged') : 1;
        
        // クエリパラメータを確実に設定
        $query->set('post_type', 'job');
        $query->set('posts_per_page', 10);
        $query->set('paged', $paged);
        $query->set('post_status', 'publish');
        
        // 404エラーを防ぐ
        $query->is_404 = false;
        
        error_log("Custom job archive query: paged={$paged}, post_type=job");
    }
}
add_action('parse_query', 'custom_job_archive_query');

/**
 * 最終的な404修正（wp_headで実行）
 */
function final_404_fix() {
    global $wp_query;
    
    if (is_404()) {
        $request_uri = $_SERVER['REQUEST_URI'];
        
        // 求人関連のURLかチェック
        if (preg_match('/^\/jobs\//', $request_uri)) {
            // ヘッダーを修正
            status_header(200);
            header('HTTP/1.1 200 OK');
            
            // 404フラグを解除
            $wp_query->is_404 = false;
            
            error_log('Final 404 fix applied for: ' . $request_uri);
        }
    }
}
add_action('wp_head', 'final_404_fix', 1);




/**
 * 給与のみ自動カンマ区切り機能（修正版）
 * 電話番号、郵便番号、住所などは対象外にする
 */

/**
 * 給与範囲を3カンマ区切りでフォーマットする関数（修正版）
 */
function format_salary_with_commas_safe($salary_range) {
    if (empty($salary_range)) {
        return '';
    }
    
    // 既にカンマが含まれている場合はそのまま返す
    if (strpos($salary_range, ',') !== false) {
        return $salary_range;
    }
    
    // 給与関連のキーワードが含まれている場合のみ処理
    $salary_keywords = array('給', '月給', '時給', '年収', '円', '万円', '～', '〜', '-');
    $has_salary_keyword = false;
    
    foreach ($salary_keywords as $keyword) {
        if (mb_strpos($salary_range, $keyword) !== false) {
            $has_salary_keyword = true;
            break;
        }
    }
    
    // 給与関連キーワードがない場合は処理しない
    if (!$has_salary_keyword) {
        return $salary_range;
    }
    
    // 明らかに給与以外のパターンを除外
    $exclude_patterns = array(
        '/^\d{3}-\d{4}$/',           // 郵便番号 (123-4567)
        '/^\d{2,4}-\d{2,4}-\d{4}$/', // 電話番号 (03-1234-5678, 090-1234-5678)
        '/〒/',                       // 郵便番号記号
        '/番地|丁目|町|市|区|県|都|府|道/', // 住所関連
        '/年|月|日/',                 // 日付関連
        '/号/',                       // 建物番号等
    );
    
    foreach ($exclude_patterns as $pattern) {
        if (preg_match($pattern, $salary_range)) {
            return $salary_range; // 除外パターンにマッチした場合は変更しない
        }
    }
    
    // 数字のみを抽出してカンマを追加する（給与パターンのみ）
    $formatted = preg_replace_callback('/(?<![\d,])\d{4,}(?![\d,])/', function($matches) {
        $number = (int)$matches[0];
        // 4桁以上の数字にカンマを付ける
        return number_format($number);
    }, $salary_range);
    
    return $formatted;
}

/**
 * 給与関連のフィールドかどうかを判定
 */
function is_salary_related_field($field_name, $content = '') {
    // 給与関連のフィールド名
    $salary_fields = array(
        'salary_range', 'salary_min', 'salary_max', 'fixed_salary', 
        'job_salary', 'salary', 'wage', 'pay'
    );
    
    // フィールド名チェック
    if (in_array($field_name, $salary_fields)) {
        return true;
    }
    
    // コンテンツに給与関連キーワードが含まれているかチェック
    if (!empty($content)) {
        $salary_keywords = array('月給', '時給', '年収', '給与', '賃金', '基本給');
        foreach ($salary_keywords as $keyword) {
            if (mb_strpos($content, $keyword) !== false) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * 出力バッファリングを使用して給与表示を自動的にカンマ区切りに変換（修正版）
 */
function start_salary_formatting_buffer_safe() {
    // 管理画面では実行しない
    if (is_admin()) {
        return;
    }
    
    // 対象ページでのみ実行
    if (is_singular('job') || 
        is_post_type_archive('job') || 
        is_page_template('page-favorites.php') ||
        is_tax(array('job_location', 'job_position', 'job_type', 'facility_type', 'job_feature')) ||
        is_front_page() ||
        is_search()) {
        
        ob_start('format_salary_in_output_safe');
    }
}

/**
 * 出力内容を解析して給与表示のみをカンマ区切りに変換（修正版）
 */
function format_salary_in_output_safe($content) {
    // エラーハンドリングを追加
    if (empty($content)) {
        return $content;
    }
    
    // より厳密なパターンマッチング（給与専用）
    $patterns = array(
        // パターン1: 明確に給与を示すパターン
        '/(\b(?:月給|時給|年収|給与|賃金)\s*)(\d{4,})(\s*円?\b)/u',
        
        // パターン2: 給与範囲（〜や～を含む）
        '/((?:月給|時給|年収|給与|賃金)\s*)(\d{4,})((?:〜|～|-)(\d{4,}))(\s*円?\b)/u',
    );
    
    foreach ($patterns as $pattern) {
        $content = preg_replace_callback($pattern, function($matches) {
            $result = $matches[0]; // 元の文字列をデフォルトとして保持
            
            // 除外パターンチェック
            $exclude_patterns = array(
                '/\d{3}-\d{4}/',        // 郵便番号
                '/\d{2,4}-\d{2,4}-\d{4}/', // 電話番号
                '/〒/',                 // 郵便番号記号
                '/年\d+月/',            // 年月
            );
            
            foreach ($exclude_patterns as $exclude) {
                if (preg_match($exclude, $matches[0])) {
                    return $result; // 除外パターンにマッチした場合は変更しない
                }
            }
            
            switch (count($matches)) {
                case 4: // パターン1用
                    if (isset($matches[2]) && is_numeric($matches[2]) && $matches[2] >= 1000) {
                        $formatted_number = number_format((int)$matches[2]);
                        $result = $matches[1] . $formatted_number . $matches[3];
                    }
                    break;
                    
                case 6: // パターン2用
                    if (isset($matches[2]) && isset($matches[4]) && 
                        is_numeric($matches[2]) && is_numeric($matches[4]) &&
                        $matches[2] >= 1000 && $matches[4] >= 1000) {
                        $formatted_first = number_format((int)$matches[2]);
                        $formatted_second = number_format((int)$matches[4]);
                        $separator = str_replace($matches[4], '', $matches[3]);
                        $result = $matches[1] . $formatted_first . $separator . $formatted_second . $matches[5];
                    }
                    break;
                    
                default:
                    // その他のパターン用
                    if (isset($matches[2]) && is_numeric($matches[2]) && $matches[2] >= 1000) {
                        $formatted_number = number_format((int)$matches[2]);
                        $result = str_replace($matches[2], $formatted_number, $matches[0]);
                    }
                    break;
            }
            
            return $result;
        }, $content);
    }
    
    return $content;
}

/**
 * より高精度な給与表示の自動フォーマット（JavaScript併用・修正版）
 */
function add_salary_formatting_javascript_safe() {
    // 管理画面では実行しない
    if (is_admin()) {
        return;
    }
    
    // 対象ページでのみ実行
    if (is_singular('job') || 
        is_post_type_archive('job') || 
        is_page_template('page-favorites.php') ||
        is_tax(array('job_location', 'job_position', 'job_type', 'facility_type', 'job_feature')) ||
        is_front_page() ||
        is_search()) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // 給与表示要素を特定してフォーマット（より限定的）
            var salarySelectors = [
                '.job-salary',
                '.salary-range', 
                '.job-sala',
                '.facility-salary',
                '.job-info-table td:contains("給与")',
                '.job-info-table td:contains("月給")', 
                '.job-info-table td:contains("時給")',
                '.inf-item:has(.fa-money-bill-wave) .job-sala',
            ];
            
            // 除外する要素（電話番号、住所等）
            var excludeSelectors = [
                '.facility-tel',
                '.facility-phone',
                '.facility-address',
                '.address',
                '.zipcode',
                '.postal-code',
                '.phone',
                '.tel'
            ];
            
            salarySelectors.forEach(function(selector) {
                $(selector).each(function() {
                    var $element = $(this);
                    var text = $element.text();
                    
                    // 除外要素かチェック
                    var isExcluded = false;
                    excludeSelectors.forEach(function(excludeSelector) {
                        if ($element.closest(excludeSelector).length > 0 || 
                            $element.hasClass(excludeSelector.replace('.', ''))) {
                            isExcluded = true;
                            return false;
                        }
                    });
                    
                    if (isExcluded) {
                        return; // 除外要素の場合はスキップ
                    }
                    
                    // 既にカンマが含まれている場合はスキップ
                    if (text.indexOf(',') !== -1) {
                        return;
                    }
                    
                    // 給与関連キーワードをチェック
                    var salaryKeywords = ['給', '月給', '時給', '年収', '円', '万円'];
                    var hasSalaryKeyword = salaryKeywords.some(function(keyword) {
                        return text.indexOf(keyword) !== -1;
                    });
                    
                    if (!hasSalaryKeyword) {
                        return; // 給与関連キーワードがない場合はスキップ
                    }
                    
                    // 除外パターンをチェック
                    var excludePatterns = [
                        /^\d{3}-\d{4}$/,           // 郵便番号
                        /^\d{2,4}-\d{2,4}-\d{4}$/, // 電話番号
                        /〒/,                       // 郵便番号記号
                        /番地|丁目|町|市|区|県|都|府|道/, // 住所
                        /年\d+月/                   // 年月
                    ];
                    
                    var isExcludedPattern = excludePatterns.some(function(pattern) {
                        return pattern.test(text);
                    });
                    
                    if (isExcludedPattern) {
                        return; // 除外パターンの場合はスキップ
                    }
                    
                    // 4桁以上の数字をカンマ区切りに変換
                    var formattedText = text.replace(/(?<![,\d])\d{4,}(?![,\d])/g, function(match) {
                        return parseInt(match).toLocaleString();
                    });
                    
                    if (formattedText !== text) {
                        $element.text(formattedText);
                    }
                });
            });
        });
        </script>
        <?php
    }
}

/**
 * メタデータ取得時に自動フォーマット（給与フィールドのみ・修正版）
 */
function auto_format_salary_meta_safe($value, $object_id, $meta_key, $single) {
    // 管理画面では実行しない
    if (is_admin()) {
        return $value;
    }
    
    // 給与関連のメタキーの場合のみ処理
    $salary_meta_keys = array('salary_range', 'salary_min', 'salary_max', 'fixed_salary');
    
    if (in_array($meta_key, $salary_meta_keys) && $single) {
        // 無限ループを防ぐため、一度だけ実行
        static $processing = array();
        $cache_key = $object_id . '_' . $meta_key;
        
        if (isset($processing[$cache_key])) {
            return $value;
        }
        
        $processing[$cache_key] = true;
        
        // 元の値を取得
        remove_filter('get_post_metadata', 'auto_format_salary_meta_safe', 10);
        $original_value = get_post_meta($object_id, $meta_key, true);
        add_filter('get_post_metadata', 'auto_format_salary_meta_safe', 10, 4);
        
        unset($processing[$cache_key]);
        
        if (!empty($original_value)) {
            return format_salary_with_commas_safe($original_value);
        }
    }
    
    return $value;
}

/**
 * エスケープ処理後のテキストも対象にする（給与のみ・修正版）
 */
function format_escaped_salary_safe($safe_text, $text) {
    // 管理画面では実行しない
    if (is_admin()) {
        return $safe_text;
    }
    
    // 給与らしいテキストかチェック（より厳密）
    if (preg_match('/(月給|時給|年収|給与).*?\d{4,}/', $text) && 
        !preg_match('/\d{3}-\d{4}/', $text) && 
        !preg_match('/\d{2,4}-\d{2,4}-\d{4}/', $text) &&
        !preg_match('/〒/', $text) &&
        !preg_match('/番地|丁目|町|市|区|県|都|府|道/', $text)) {
        
        // 4桁以上の数字をカンマ区切りに
        $formatted = preg_replace_callback('/(?<![,\d])\d{4,}(?![,\d])/', function($matches) {
            return number_format((int)$matches[0]);
        }, $safe_text);
        
        return $formatted;
    }
    
    return $safe_text;
}

/**
 * より汎用的なテキスト処理（給与のみ・修正版）
 */
function format_content_salary_safe($content) {
    // 管理画面では実行しない
    if (is_admin()) {
        return $content;
    }
    
    // 求人投稿タイプでのみ実行
    if (get_post_type() === 'job') {
        // 給与関連のキーワードを含む4桁以上の数字のみフォーマット
        $content = preg_replace_callback('/((月給|時給|年収|給与)[^0-9]*?)(\d{4,})/', function($matches) {
            // 除外パターンチェック
            if (preg_match('/\d{3}-\d{4}/', $matches[0]) || 
                preg_match('/\d{2,4}-\d{2,4}-\d{4}/', $matches[0]) ||
                preg_match('/〒/', $matches[0]) ||
                preg_match('/番地|丁目|町|市|区|県|都|府|道/', $matches[0])) {
                return $matches[0]; // 除外パターンにマッチした場合は変更しない
            }
            
            return $matches[1] . $matches[2] . number_format((int)$matches[3]);
        }, $content);
    }
    
    return $content;
}

/**
 * デバッグ用：どの方法が有効か確認（修正版）
 */
function debug_salary_formatting_safe() {
    if (is_singular('job') && current_user_can('administrator')) {
        echo '<!-- Salary formatting debug (safe version): Active -->';
    }
}

// WordPressフックに関数を登録
add_action('template_redirect', 'start_salary_formatting_buffer_safe');
add_action('wp_footer', 'add_salary_formatting_javascript_safe');
add_filter('get_post_metadata', 'auto_format_salary_meta_safe', 10, 4);
add_filter('esc_html', 'format_escaped_salary_safe', 10, 2);
add_filter('the_content', 'format_content_salary_safe');
add_filter('the_excerpt', 'format_content_salary_safe');

// デバッグ機能（WP_DEBUGが有効な場合のみ）
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', 'debug_salary_formatting_safe');
}



/**
 * 求人検索結果ページ対策
 * 
 */

/**
 * 求人検索結果ページのメタディスクリプション・タイトル自動設定
 */
function set_job_archive_seo() {
    // 求人関連ページでのみ実行
    if (!is_post_type_archive('job') && !is_tax(array('job_location', 'job_position', 'job_type', 'facility_type', 'job_feature'))) {
        return;
    }
    
    $site_name = get_bloginfo('name');
    $current_url = home_url($_SERVER['REQUEST_URI']);
    
    // SEOデータを取得
    $seo_data = get_simple_seo_data();
    
    // メタタグを出力
    add_action('wp_head', function() use ($seo_data, $current_url) {
        echo '<title>' . esc_html($seo_data['title']) . '</title>' . "\n";
        echo '<meta name="description" content="' . esc_attr($seo_data['description']) . '">' . "\n";
        echo '<link rel="canonical" href="' . esc_url($current_url) . '">' . "\n";
        
        // Open Graph
        echo '<meta property="og:title" content="' . esc_attr($seo_data['title']) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($seo_data['description']) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($current_url) . '">' . "\n";
        echo '<meta property="og:type" content="website">' . "\n";
        
        // 検索エンジン向け
        echo '<meta name="robots" content="index,follow">' . "\n";
        
        // 求人専用メタタグ
        echo '<meta name="job:count" content="' . esc_attr($seo_data['job_count']) . '">' . "\n";
        if (!empty($seo_data['location'])) {
            echo '<meta name="job:location" content="' . esc_attr($seo_data['location']) . '">' . "\n";
        }
    }, 1);
}
add_action('wp', 'set_job_archive_seo');

/**
 * 簡単なSEOデータ取得
 */
function get_simple_seo_data() {
    $site_name = get_bloginfo('name');
    $base_text = '放課後等デイサービス・児童発達支援の求人情報。正社員・パート・アルバイト募集中。';
    
    // 基本データ
    $seo_data = array(
        'title' => '',
        'description' => '',
        'job_count' => 0,
        'location' => ''
    );
    
    // 地域ページ
    if (is_tax('job_location')) {
        $term = get_queried_object();
        $job_count = get_simple_job_count($term);
        $location_name = $term->name;
        
        $seo_data['title'] = "{$location_name}の放課後等デイサービス求人{$job_count}件｜{$site_name}";
        $seo_data['description'] = "{$location_name}エリアの放課後等デイサービス・児童発達支援の求人を{$job_count}件掲載中。{$base_text}地域密着型の職場で一緒に働きませんか？";
        $seo_data['job_count'] = $job_count;
        $seo_data['location'] = $location_name;
    }
    // 職種ページ
    elseif (is_tax('job_position')) {
        $term = get_queried_object();
        $job_count = get_simple_job_count($term);
        $position_name = $term->name;
        
        $seo_data['title'] = "{$position_name}の求人{$job_count}件｜放課後等デイサービス｜{$site_name}";
        $seo_data['description'] = "放課後等デイサービス・児童発達支援での{$position_name}の求人を{$job_count}件掲載中。{$base_text}未経験者歓迎の求人も多数あります。";
        $seo_data['job_count'] = $job_count;
    }
    // 雇用形態ページ
    elseif (is_tax('job_type')) {
        $term = get_queried_object();
        $job_count = get_simple_job_count($term);
        $type_name = $term->name;
        
        $seo_data['title'] = "{$type_name}の求人{$job_count}件｜放課後等デイサービス｜{$site_name}";
        $seo_data['description'] = "放課後等デイサービス・児童発達支援での{$type_name}の求人を{$job_count}件掲載中。{$base_text}ライフスタイルに合わせた働き方を見つけられます。";
        $seo_data['job_count'] = $job_count;
    }
    // 施設形態ページ
    elseif (is_tax('facility_type')) {
        $term = get_queried_object();
        $job_count = get_simple_job_count($term);
        $facility_name = $term->name;
        
        $seo_data['title'] = "{$facility_name}の求人{$job_count}件｜{$site_name}";
        $seo_data['description'] = "{$facility_name}の求人を{$job_count}件掲載中。{$base_text}充実した研修制度で安心してスタートできます。";
        $seo_data['job_count'] = $job_count;
    }
    // 特徴ページ
    elseif (is_tax('job_feature')) {
        $term = get_queried_object();
        $job_count = get_simple_job_count($term);
        $feature_name = $term->name;
        
        $seo_data['title'] = "{$feature_name}の求人{$job_count}件｜放課後等デイサービス｜{$site_name}";
        $seo_data['description'] = "{$feature_name}の特徴を持つ放課後等デイサービス・児童発達支援の求人を{$job_count}件掲載中。{$base_text}";
        $seo_data['job_count'] = $job_count;
    }
    // メイン求人アーカイブ
    elseif (is_post_type_archive('job')) {
        $total_jobs = wp_count_posts('job')->publish;
        
        $seo_data['title'] = "放課後等デイサービス・児童発達支援の求人{$total_jobs}件｜{$site_name}";
        $seo_data['description'] = "全国の放課後等デイサービス・児童発達支援の求人を{$total_jobs}件掲載中。{$base_text}毎日更新で最新の求人情報をお届けします。";
        $seo_data['job_count'] = $total_jobs;
    }
    
    return $seo_data;
}

/**
 * 簡単な求人数取得
 */
function get_simple_job_count($term = null) {
    if (!$term) {
        $count = wp_count_posts('job');
        return $count->publish;
    }
    
    $args = array(
        'post_type' => 'job',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => $term->taxonomy,
                'field' => 'term_id',
                'terms' => $term->term_id
            )
        ),
        'fields' => 'ids'
    );
    
    $query = new WP_Query($args);
    $count = $query->found_posts;
    wp_reset_postdata();
    
    return $count;
}

/**
 * 求人検索結果ページ用の構造化データ（JSON-LD）
 */
function add_simple_job_structured_data() {
    if (!is_post_type_archive('job') && !is_tax(array('job_location', 'job_position', 'job_type', 'facility_type', 'job_feature'))) {
        return;
    }
    
    $site_name = get_bloginfo('name');
    $seo_data = get_simple_seo_data();
    
    // WebSiteの構造化データ（サイト内検索）
    $website_schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $site_name,
        'url' => home_url(),
        'potentialAction' => array(
            '@type' => 'SearchAction',
            'target' => array(
                '@type' => 'EntryPoint',
                'urlTemplate' => home_url('/?s={search_term_string}&post_type=job')
            ),
            'query-input' => 'required name=search_term_string'
        )
    );
    
    // 組織の構造化データ
    $organization_schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $site_name,
        'url' => home_url(),
        'description' => '放課後等デイサービス・児童発達支援の求人情報サイト',
        'contactPoint' => array(
            '@type' => 'ContactPoint',
            'contactType' => 'customer service',
            'availableLanguage' => 'Japanese'
        )
    );
    
    // パンくずリストの構造化データ
    $breadcrumb_schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => array(
            array(
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'ホーム',
                'item' => home_url('/')
            ),
            array(
                '@type' => 'ListItem',
                'position' => 2,
                'name' => '求人情報',
                'item' => home_url('/jobs/')
            )
        )
    );
    
    // 現在のページ情報を追加
    if (is_tax()) {
        $term = get_queried_object();
        $breadcrumb_schema['itemListElement'][] = array(
            '@type' => 'ListItem',
            'position' => 3,
            'name' => $term->name,
            'item' => get_term_link($term)
        );
    }
    
    // 出力
    echo '<script type="application/ld+json">' . "\n";
    echo wp_json_encode($website_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo "\n" . '</script>' . "\n";
    
    echo '<script type="application/ld+json">' . "\n";
    echo wp_json_encode($organization_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo "\n" . '</script>' . "\n";
    
    echo '<script type="application/ld+json">' . "\n";
    echo wp_json_encode($breadcrumb_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo "\n" . '</script>' . "\n";
}
add_action('wp_head', 'add_simple_job_structured_data', 5);

/**
 * 地域ページに地域情報を自動追加
 */
function add_location_seo_content() {
    if (!is_tax('job_location')) {
        return;
    }
    
    $term = get_queried_object();
    $job_count = get_simple_job_count($term);
    $location_name = $term->name;
    
    // ページ下部にSEO用コンテンツを追加
    add_action('wp_footer', function() use ($location_name, $job_count) {
        echo '<div class="location-seo-content" style="background:#f8f9fa; padding:40px 20px; margin:30px 0; border-radius:8px;">';
        echo '<div style="max-width:800px; margin:0 auto;">';
        
        echo '<h2 style="color:#333; margin-bottom:20px; text-align:center;">' . esc_html($location_name) . 'の放課後等デイサービス求人の特徴</h2>';
        
        echo '<p style="line-height:1.6; color:#666; margin-bottom:20px;">';
        echo esc_html($location_name) . 'エリアでは現在' . esc_html($job_count) . '件の放課後等デイサービス・児童発達支援の求人があります。';
        echo 'この地域は子育て支援が充実しており、福祉施設で働く方にとって働きやすい環境が整っています。';
        echo '</p>';
        
        echo '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:20px; margin:30px 0;">';
        
        echo '<div style="background:white; padding:20px; border-radius:6px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">';
        echo '<h3 style="color:#007cba; margin-bottom:10px; font-size:16px;">地域密着型</h3>';
        echo '<p style="margin:0; font-size:14px; color:#666;">アットホームな環境で働けます</p>';
        echo '</div>';
        
        echo '<div style="background:white; padding:20px; border-radius:6px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">';
        echo '<h3 style="color:#007cba; margin-bottom:10px; font-size:16px;">交通便利</h3>';
        echo '<p style="margin:0; font-size:14px; color:#666;">駅から近い施設が多数</p>';
        echo '</div>';
        
        echo '<div style="background:white; padding:20px; border-radius:6px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">';
        echo '<h3 style="color:#007cba; margin-bottom:10px; font-size:16px;">研修充実</h3>';
        echo '<p style="margin:0; font-size:14px; color:#666;">未経験でも安心のサポート</p>';
        echo '</div>';
        
        echo '</div>';
        
        echo '<h3 style="color:#333; margin:30px 0 15px 0;">よくある質問</h3>';
        echo '<div style="background:white; padding:20px; border-radius:6px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">';
        echo '<p style="margin:0 0 10px 0; font-weight:bold; color:#333;">Q. ' . esc_html($location_name) . 'エリアの求人の特徴は？</p>';
        echo '<p style="margin:0; color:#666; font-size:14px;">A. 地域密着型の施設が多く、職員同士の連携が取りやすい環境です。交通アクセスも良好で通勤しやすいのが特徴です。</p>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    });
}
add_action('wp', 'add_location_seo_content');

/**
 * サイトマップに求人カテゴリを追加
 */
function add_job_terms_to_sitemap($urls, $type) {
    if ($type !== 'term') {
        return $urls;
    }
    
    // 主要な地域ページを追加
    $location_terms = get_terms(array(
        'taxonomy' => 'job_location',
        'hide_empty' => false,
        'number' => 50,
        'orderby' => 'count',
        'order' => 'DESC'
    ));
    
    foreach ($location_terms as $term) {
        $urls[] = array(
            'loc' => get_term_link($term),
            'lastmod' => date('Y-m-d'),
            'priority' => 0.8
        );
    }
    
    // 職種ページを追加
    $position_terms = get_terms(array(
        'taxonomy' => 'job_position',
        'hide_empty' => false
    ));
    
    foreach ($position_terms as $term) {
        $urls[] = array(
            'loc' => get_term_link($term),
            'lastmod' => date('Y-m-d'),
            'priority' => 0.7
        );
    }
    
    return $urls;
}
add_filter('wp_sitemaps_taxonomies_entry', 'add_job_terms_to_sitemap', 10, 2);

/**
 * タイトルタグの最適化
 */
function optimize_job_page_titles($title_parts) {
    if (is_tax('job_location')) {
        $term = get_queried_object();
        $job_count = get_simple_job_count($term);
        $title_parts['title'] = $term->name . 'の放課後等デイサービス求人' . $job_count . '件';
        
    } elseif (is_tax('job_position')) {
        $term = get_queried_object();
        $job_count = get_simple_job_count($term);
        $title_parts['title'] = $term->name . '求人' . $job_count . '件｜放課後等デイサービス';
        
    } elseif (is_post_type_archive('job')) {
        $total_jobs = wp_count_posts('job')->publish;
        $title_parts['title'] = '放課後等デイサービス求人' . $total_jobs . '件｜正社員・パート募集';
    }
    
    return $title_parts;
}
add_filter('document_title_parts', 'optimize_job_page_titles');

/**
 * 関連する求人ページへの内部リンクを自動生成
 */
function add_related_job_links() {
    if (is_tax('job_location')) {
        $current_term = get_queried_object();
        
        // 同じ都道府県の他の地域
        $related_terms = get_terms(array(
            'taxonomy' => 'job_location',
            'parent' => $current_term->parent,
            'exclude' => array($current_term->term_id),
            'number' => 6,
            'hide_empty' => false
        ));
        
        if (!empty($related_terms)) {
            add_action('wp_footer', function() use ($related_terms) {
                echo '<div style="background:#fff; padding:30px 20px; margin:20px 0; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">';
                echo '<h3 style="color:#333; margin-bottom:20px; text-align:center;">関連する地域の求人</h3>';
                echo '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:10px;">';
                
                foreach ($related_terms as $term) {
                    $job_count = get_simple_job_count($term);
                    echo '<a href="' . esc_url(get_term_link($term)) . '" style="display:block; padding:10px; background:#f8f9fa; color:#007cba; text-decoration:none; border-radius:4px; text-align:center; border:1px solid #ddd; transition:all 0.3s;">';
                    echo esc_html($term->name) . '<br><small>(' . $job_count . '件)</small>';
                    echo '</a>';
                }
                
                echo '</div>';
                echo '</div>';
            });
        }
    }
}
add_action('wp', 'add_related_job_links');

/**
 * ページ読み込み速度の最適化
 */
function optimize_job_page_speed() {
    if (is_post_type_archive('job') || is_tax(array('job_location', 'job_position', 'job_type', 'facility_type', 'job_feature'))) {
        
        // 不要なWordPressスクリプトを除去
        add_action('wp_enqueue_scripts', function() {
            wp_dequeue_script('wp-embed');
        }, 100);
        
        // 重要でないCSSを遅延読み込み
        add_action('wp_head', function() {
            echo '<style>
            /* 重要なスタイルのみ */
            .job-card { margin-bottom: 20px; }
            .job-title { font-size: 18px; color: #333; }
            .job-description { color: #666; line-height: 1.6; }
            </style>';
        }, 1);
    }
}
add_action('wp', 'optimize_job_page_speed');




/**
 * エラー修正版：全求人ページのインデックス促進
 * functions.phpに追加してください（重複チェック付き）
 */

/**
 * 1. 全ての求人URL構造を受け入れる
 */
if (!function_exists('accept_all_job_urls_v2')) {
    function accept_all_job_urls_v2() {
        // どんなパターンでも求人詳細ページとして認識
        add_rewrite_rule(
            'jobs/(.+)/([0-9]+)/?$',
            'index.php?post_type=job&p=$matches[2]',
            'top'
        );
        
        add_rewrite_rule(
            'jobs/([0-9]+)/?$',
            'index.php?post_type=job&p=$matches[1]',
            'top'
        );
    }
    add_action('init', 'accept_all_job_urls_v2', 1);
}

/**
 * 2. 現在のURLをそのまま正規URLとして使用
 */
if (!function_exists('use_current_url_as_canonical_v2')) {
    function use_current_url_as_canonical_v2() {
        if (is_singular('job')) {
            // 他のcanonicalタグを全て削除
            remove_action('wp_head', 'rel_canonical');
            remove_action('wp_head', 'wp_shortlink_wp_head');
            
            // SEOプラグインのcanonicalも削除
            add_filter('wpseo_canonical', '__return_false');
            add_filter('rank_math/frontend/canonical', '__return_false');
            
            // 現在のURLをcanonicalとして設定
            $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            $canonical_url = rtrim($current_url, '/') . '/';
            
            echo '<link rel="canonical" href="' . esc_url($canonical_url) . '" />' . "\n";
        }
    }
    add_action('wp_head', 'use_current_url_as_canonical_v2', 1);
}

/**
 * 3. 強制的にインデックス許可
 */
if (!function_exists('force_job_indexing_v2')) {
    function force_job_indexing_v2() {
        if (is_singular('job')) {
            // 全てのnoindexを削除
            remove_action('wp_head', 'wp_no_robots');
            remove_action('wp_head', 'noindex', 1);
            
            // SEOプラグインのnoindexも無効化
            add_filter('wpseo_robots', function() {
                return 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1';
            });
            
            add_filter('rank_math/frontend/robots', function() {
                return 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1';
            });
            
            // 確実にindexタグを出力
            echo '<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />' . "\n";
        }
    }
    add_action('wp_head', 'force_job_indexing_v2', 0);
}

/**
 * 4. 構造化データを現在URLで出力（既存関数と重複しないよう名前変更）
 */
if (!function_exists('output_job_schema_data')) {
    function output_job_schema_data() {
        if (is_singular('job')) {
            global $post;
            
            $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            $canonical_url = rtrim($current_url, '/') . '/';
            
            $facility_name = get_post_meta($post->ID, 'facility_name', true);
            $facility_company = get_post_meta($post->ID, 'facility_company', true);
            $facility_address = get_post_meta($post->ID, 'facility_address', true);
            $salary_range = get_post_meta($post->ID, 'salary_range', true);
            
            $job_positions = get_the_terms($post->ID, 'job_position');
            $job_locations = get_the_terms($post->ID, 'job_location');
            
            $structured_data = array(
                '@context' => 'https://schema.org/',
                '@type' => 'JobPosting',
                '@id' => $canonical_url . '#job',
                'url' => $canonical_url,
                'title' => get_the_title(),
                'description' => wp_strip_all_tags(get_the_excerpt() ?: substr(get_the_content(), 0, 300)),
                'identifier' => array(
                    '@type' => 'PropertyValue',
                    'name' => 'Job ID',
                    'value' => $post->ID
                ),
                'datePosted' => get_the_date('c'),
                'validThrough' => date('c', strtotime('+6 months')),
                'hiringOrganization' => array(
                    '@type' => 'Organization',
                    'name' => $facility_company ?: $facility_name ?: 'こどもプラス',
                    'sameAs' => home_url()
                ),
                'jobLocation' => array(
                    '@type' => 'Place',
                    'address' => array(
                        '@type' => 'PostalAddress',
                        'streetAddress' => $facility_address,
                        'addressLocality' => $job_locations && !is_wp_error($job_locations) ? $job_locations[0]->name : '',
                        'addressCountry' => 'JP'
                    )
                ),
                'employmentType' => 'FULL_TIME'
            );
            
            // 職種情報
            if ($job_positions && !is_wp_error($job_positions)) {
                $structured_data['occupationalCategory'] = $job_positions[0]->name;
            }
            
            // 給与情報
            if ($salary_range) {
                $structured_data['baseSalary'] = array(
                    '@type' => 'MonetaryAmount',
                    'currency' => 'JPY',
                    'value' => array(
                        '@type' => 'QuantitativeValue',
                        'unitText' => 'MONTH',
                        'value' => $salary_range
                    )
                );
            }
            
            echo '<script type="application/ld+json">';
            echo json_encode($structured_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            echo '</script>' . "\n";
        }
    }
    add_action('wp_head', 'output_job_schema_data', 15);
}

/**
 * 5. サイトマップで全求人URLを現在の形式で出力
 */
if (!function_exists('include_all_jobs_in_sitemap')) {
    function include_all_jobs_in_sitemap($entry, $post) {
        if ($post->post_type === 'job' && $post->post_status === 'publish') {
            // 現在のパーマリンク構造をそのまま使用
            $entry['loc'] = get_permalink($post->ID);
            $entry['lastmod'] = get_the_modified_date('c', $post->ID);
            $entry['changefreq'] = 'weekly';
            $entry['priority'] = 0.8;
        }
        return $entry;
    }
    add_filter('wp_sitemaps_posts_entry', 'include_all_jobs_in_sitemap', 99, 2);
}

/**
 * 6. robots.txtで/jobs/を明示的に許可
 */
if (!function_exists('allow_jobs_directory_in_robots')) {
    function allow_jobs_directory_in_robots($output, $public) {
        if ($public) {
            $output .= "\n# Job pages - Allow all\n";
            $output .= "Allow: /jobs/\n";
            $output .= "Allow: /jobs/*\n";
            $output .= "\n# Sitemaps\n";
            $output .= "Sitemap: " . home_url('/wp-sitemap.xml') . "\n";
            $output .= "Sitemap: " . home_url('/sitemap.xml') . "\n";
            $output .= "Sitemap: " . home_url('/sitemap_index.xml') . "\n";
        }
        return $output;
    }
    add_filter('robots_txt', 'allow_jobs_directory_in_robots', 10, 2);
}

/**
 * 7. 内部リンク強化
 */
if (!function_exists('enhance_job_internal_links')) {
    function enhance_job_internal_links() {
        if (is_singular('job')) {
            global $post;
            
            echo '<!-- Internal links for job indexing -->' . "\n";
            echo '<link rel="up" href="' . home_url('/jobs/') . '" />' . "\n";
            
            // 最新求人へのリンク
            $recent_jobs = get_posts(array(
                'post_type' => 'job',
                'posts_per_page' => 5,
                'post__not_in' => array($post->ID),
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            foreach ($recent_jobs as $job) {
                echo '<link rel="related" href="' . get_permalink($job->ID) . '" />' . "\n";
            }
            
            // タクソノミーページへのリンク
            $job_locations = get_the_terms($post->ID, 'job_location');
            $job_positions = get_the_terms($post->ID, 'job_position');
            
            if ($job_locations && !is_wp_error($job_locations)) {
                foreach ($job_locations as $location) {
                    echo '<link rel="related" href="' . get_term_link($location) . '" />' . "\n";
                }
            }
            
            if ($job_positions && !is_wp_error($job_positions)) {
                foreach ($job_positions as $position) {
                    echo '<link rel="related" href="' . get_term_link($position) . '" />' . "\n";
                }
            }
        }
    }
    add_action('wp_head', 'enhance_job_internal_links', 25);
}

/**
 * 8. ページタイトルの最適化
 */
if (!function_exists('optimize_job_seo_title')) {
    function optimize_job_seo_title($title) {
        if (is_singular('job')) {
            global $post;
            
            $facility_name = get_post_meta($post->ID, 'facility_name', true);
            $job_positions = get_the_terms($post->ID, 'job_position');
            $job_locations = get_the_terms($post->ID, 'job_location');
            
            $position_name = $job_positions && !is_wp_error($job_positions) ? $job_positions[0]->name : '';
            $location_name = $job_locations && !is_wp_error($job_locations) ? $job_locations[0]->name : '';
            
            if ($facility_name && $position_name && $location_name) {
                $title = $facility_name . 'の' . $position_name . '求人（' . $location_name . '）｜' . get_bloginfo('name');
            } elseif ($facility_name && $position_name) {
                $title = $facility_name . 'の' . $position_name . '求人｜' . get_bloginfo('name');
            } elseif ($facility_name) {
                $title = $facility_name . '｜求人情報｜' . get_bloginfo('name');
            }
        }
        return $title;
    }
    add_filter('pre_get_document_title', 'optimize_job_seo_title');
}

/**
 * 9. メタディスクリプションの最適化
 */
if (!function_exists('add_job_meta_description_tag')) {
    function add_job_meta_description_tag() {
        if (is_singular('job')) {
            global $post;
            
            $facility_name = get_post_meta($post->ID, 'facility_name', true);
            $salary_range = get_post_meta($post->ID, 'salary_range', true);
            $job_positions = get_the_terms($post->ID, 'job_position');
            $job_locations = get_the_terms($post->ID, 'job_location');
            
            $position_name = $job_positions && !is_wp_error($job_positions) ? $job_positions[0]->name : '';
            $location_name = $job_locations && !is_wp_error($job_locations) ? $job_locations[0]->name : '';
            
            $description = '';
            if ($facility_name && $position_name && $location_name) {
                $description = $location_name . 'の' . $facility_name . 'で' . $position_name . 'を募集';
                if ($salary_range) {
                    $description .= '｜給与：' . $salary_range;
                }
                $description .= '｜こどもプラス求人サイト';
            }
            
            if ($description) {
                echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
            }
        }
    }
    add_action('wp_head', 'add_job_meta_description_tag', 5);
}

/**
 * 10. 求人投稿保存時にサイトマップ更新
 */
if (!function_exists('refresh_sitemap_on_job_update')) {
    function refresh_sitemap_on_job_update($post_id, $post, $update) {
        if ($post->post_type === 'job' && $post->post_status === 'publish') {
            // サイトマップキャッシュをクリア
            if (class_exists('WPSEO_Sitemaps')) {
                WPSEO_Sitemaps::invalidate_cache();
            }
            
            if (class_exists('RankMath')) {
                \RankMath\Sitemap\Cache::invalidate();
            }
            
            wp_cache_delete('sitemaps', 'core');
            
            // Googleにサイトマップ更新を通知（遅延実行）
            wp_schedule_single_event(time() + 60, 'ping_google_sitemap_update');
        }
    }
    add_action('save_post', 'refresh_sitemap_on_job_update', 10, 3);
}

/**
 * 11. Google サイトマップ通知
 */
if (!function_exists('ping_google_sitemap_update')) {
    function ping_google_sitemap_update() {
        $sitemap_urls = array(
            home_url('/wp-sitemap.xml'),
            home_url('/sitemap.xml'),
            home_url('/sitemap_index.xml')
        );
        
        foreach ($sitemap_urls as $sitemap_url) {
            $ping_url = 'https://www.google.com/ping?sitemap=' . urlencode($sitemap_url);
            wp_remote_get($ping_url, array('timeout' => 10));
        }
    }
    add_action('ping_google_sitemap_update', 'ping_google_sitemap_update');
}

/**
 * 12. リライトルールフラッシュ
 */
if (!function_exists('flush_job_indexing_rewrite_rules')) {
    function flush_job_indexing_rewrite_rules() {
        if (!get_option('job_indexing_rules_flushed_v2')) {
            flush_rewrite_rules();
            update_option('job_indexing_rules_flushed_v2', true);
        }
    }
    add_action('init', 'flush_job_indexing_rewrite_rules', 99);
}





/**
 * 都道府県別教室一覧のSEO対応リライトルール
 * functions.phpに追加してください
 */

// 都道府県マッピング
function get_prefecture_mapping() {
    return array(
        'hokkaido' => array('id' => 1, 'name' => '北海道'),
        'aomori' => array('id' => 2, 'name' => '青森県'),
        'iwate' => array('id' => 3, 'name' => '岩手県'),
        'miyagi' => array('id' => 4, 'name' => '宮城県'),
        'akita' => array('id' => 5, 'name' => '秋田県'),
        'yamagata' => array('id' => 6, 'name' => '山形県'),
        'fukushima' => array('id' => 7, 'name' => '福島県'),
        'ibaraki' => array('id' => 8, 'name' => '茨城県'),
        'tochigi' => array('id' => 9, 'name' => '栃木県'),
        'gunma' => array('id' => 10, 'name' => '群馬県'),
        'saitama' => array('id' => 11, 'name' => '埼玉県'),
        'chiba' => array('id' => 12, 'name' => '千葉県'),
        'tokyo' => array('id' => 13, 'name' => '東京都'),
        'kanagawa' => array('id' => 14, 'name' => '神奈川県'),
        'niigata' => array('id' => 15, 'name' => '新潟県'),
        'toyama' => array('id' => 16, 'name' => '富山県'),
        'ishikawa' => array('id' => 17, 'name' => '石川県'),
        'fukui' => array('id' => 18, 'name' => '福井県'),
        'yamanashi' => array('id' => 19, 'name' => '山梨県'),
        'nagano' => array('id' => 20, 'name' => '長野県'),
        'gifu' => array('id' => 21, 'name' => '岐阜県'),
        'shizuoka' => array('id' => 22, 'name' => '静岡県'),
        'aichi' => array('id' => 23, 'name' => '愛知県'),
        'mie' => array('id' => 24, 'name' => '三重県'),
        'shiga' => array('id' => 25, 'name' => '滋賀県'),
        'kyoto' => array('id' => 26, 'name' => '京都府'),
        'osaka' => array('id' => 27, 'name' => '大阪府'),
        'hyogo' => array('id' => 28, 'name' => '兵庫県'),
        'nara' => array('id' => 29, 'name' => '奈良県'),
        'wakayama' => array('id' => 30, 'name' => '和歌山県'),
        'tottori' => array('id' => 31, 'name' => '鳥取県'),
        'shimane' => array('id' => 32, 'name' => '島根県'),
        'okayama' => array('id' => 33, 'name' => '岡山県'),
        'hiroshima' => array('id' => 34, 'name' => '広島県'),
        'yamaguchi' => array('id' => 35, 'name' => '山口県'),
        'tokushima' => array('id' => 36, 'name' => '徳島県'),
        'kagawa' => array('id' => 37, 'name' => '香川県'),
        'ehime' => array('id' => 38, 'name' => '愛媛県'),
        'kochi' => array('id' => 39, 'name' => '高知県'),
        'fukuoka' => array('id' => 40, 'name' => '福岡県'),
        'saga' => array('id' => 41, 'name' => '佐賀県'),
        'nagasaki' => array('id' => 42, 'name' => '長崎県'),
        'kumamoto' => array('id' => 43, 'name' => '熊本県'),
        'oita' => array('id' => 44, 'name' => '大分県'),
        'miyazaki' => array('id' => 45, 'name' => '宮崎県'),
        'kagoshima' => array('id' => 46, 'name' => '鹿児島県'),
        'okinawa' => array('id' => 47, 'name' => '沖縄県'),
    );
}

/**
 * 都道府県別ページのリライトルールを追加
 */
function add_prefecture_rewrite_rules() {
    $prefectures = get_prefecture_mapping();
    
    foreach ($prefectures as $slug => $data) {
        add_rewrite_rule(
            '^hokago-day-service/' . $slug . '/?$',
            'index.php?pagename=list&prefecture=' . $slug,
            'top'
        );
    }
    
    // メインページのルール
    add_rewrite_rule(
        '^hokago-day-service/?$',
        'index.php?pagename=list',
        'top'
    );
}
add_action('init', 'add_prefecture_rewrite_rules');

/**
 * カスタムクエリ変数を追加
 */
function add_prefecture_query_vars($vars) {
    $vars[] = 'prefecture';
    return $vars;
}
add_filter('query_vars', 'add_prefecture_query_vars');

/**
 * 都道府県別ページのSEO対策
 */
function set_prefecture_seo_data() {
    $prefecture_slug = get_query_var('prefecture');
    
    if ($prefecture_slug) {
        $prefectures = get_prefecture_mapping();
        
        if (isset($prefectures[$prefecture_slug])) {
            $pref_name = $prefectures[$prefecture_slug]['name'];
            $site_name = get_bloginfo('name');
            
            // タイトルタグ
            $title = "放課後等デイサービス・児童発達支援 {$pref_name}の事業所の一覧｜{$site_name}";
            
            // メタディスクリプション
            $description = "{$pref_name}の放課後等デイサービス・児童発達支援の事業所の一覧でご紹介。こどもプラス加盟教室の所在地・連絡先・求人情報をまとめて確認できます。{$pref_name}で放課後等デイサービスをお探しなら{$site_name}へ。";
            
            // 現在のURL
            $current_url = home_url('/hokago-day-service/' . $prefecture_slug . '/');
            
            add_action('wp_head', function() use ($title, $description, $current_url, $pref_name) {
                echo '<title>' . esc_html($title) . '</title>' . "\n";
                echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
                echo '<link rel="canonical" href="' . esc_url($current_url) . '">' . "\n";
                
                // Open Graph
                echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
                echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
                echo '<meta property="og:url" content="' . esc_url($current_url) . '">' . "\n";
                echo '<meta property="og:type" content="website">' . "\n";
                
                // 地域特化のメタタグ
                echo '<meta name="geo.region" content="JP-' . get_prefecture_code($pref_name) . '">' . "\n";
                echo '<meta name="geo.placename" content="' . esc_attr($pref_name) . '">' . "\n";
                
                // 構造化データ
                $structured_data = array(
                    '@context' => 'https://schema.org',
                    '@type' => 'WebPage',
                    'name' => $title,
                    'description' => $description,
                    'url' => $current_url,
                    'about' => array(
                        '@type' => 'Service',
                        'name' => '放課後等デイサービス',
                        'description' => $pref_name . 'の放課後等デイサービスの事業所の一覧',
                        'areaServed' => array(
                            '@type' => 'Place',
                            'name' => $pref_name
                        )
                    ),
                    'breadcrumb' => array(
                        '@type' => 'BreadcrumbList',
                        'itemListElement' => array(
                            array(
                                '@type' => 'ListItem',
                                'position' => 1,
                                'name' => 'ホーム',
                                'item' => home_url()
                            ),
                            array(
                                '@type' => 'ListItem',
                                'position' => 2,
                                'name' => '放課後等デイサービスの事業所の一覧',
                                'item' => home_url('/hokago-day-service/')
                            ),
                            array(
                                '@type' => 'ListItem',
                                'position' => 3,
                                'name' => $pref_name,
                                'item' => $current_url
                            )
                        )
                    )
                );
                
                echo '<script type="application/ld+json">';
                echo json_encode($structured_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                echo '</script>' . "\n";
            }, 1);
        }
    }
}
add_action('wp', 'set_prefecture_seo_data');

/**
 * 都道府県コードを取得するヘルパー関数
 */
function get_prefecture_code($pref_name) {
    $codes = array(
        '北海道' => '01', '青森県' => '02', '岩手県' => '03', '宮城県' => '04',
        '秋田県' => '05', '山形県' => '06', '福島県' => '07', '茨城県' => '08',
        '栃木県' => '09', '群馬県' => '10', '埼玉県' => '11', '千葉県' => '12',
        '東京都' => '13', '神奈川県' => '14', '新潟県' => '15', '富山県' => '16',
        '石川県' => '17', '福井県' => '18', '山梨県' => '19', '長野県' => '20',
        '岐阜県' => '21', '静岡県' => '22', '愛知県' => '23', '三重県' => '24',
        '滋賀県' => '25', '京都府' => '26', '大阪府' => '27', '兵庫県' => '28',
        '奈良県' => '29', '和歌山県' => '30', '鳥取県' => '31', '島根県' => '32',
        '岡山県' => '33', '広島県' => '34', '山口県' => '35', '徳島県' => '36',
        '香川県' => '37', '愛媛県' => '38', '高知県' => '39', '福岡県' => '40',
        '佐賀県' => '41', '長崎県' => '42', '熊本県' => '43', '大分県' => '44',
        '宮崎県' => '45', '鹿児島県' => '46', '沖縄県' => '47'
    );
    
    return isset($codes[$pref_name]) ? $codes[$pref_name] : '00';
}

/**
 * リライトルールの強制更新
 */
function flush_prefecture_rewrite_rules() {
    if (!get_option('prefecture_rules_flushed')) {
        flush_rewrite_rules();
        update_option('prefecture_rules_flushed', true);
    }
}
add_action('init', 'flush_prefecture_rewrite_rules', 999);



/**
 * 都道府県別ページのサイトマップ完全対応版
 * functions.phpに追加してください
 */

/**
 * 1. WordPressサイトマップに都道府県ページを追加（改善版）
 */
function register_prefecture_sitemap_provider_enhanced() {
    if (class_exists('WP_Sitemaps')) {
        wp_register_sitemap_provider('prefecture_pages', new WP_Sitemaps_Prefecture_Enhanced());
    }
}
add_action('init', 'register_prefecture_sitemap_provider_enhanced', 11);

/**
 * 都道府県ページ専用サイトマッププロバイダー（改善版）
 */
class WP_Sitemaps_Prefecture_Enhanced extends WP_Sitemaps_Provider {
    
    public function get_name() {
        return 'prefecture_pages';
    }
    
    public function get_url_list($page_num, $object_subtype = '') {
        $prefectures = get_prefecture_mapping();
        $url_list = array();
        
        // メインページ（教室一覧トップ）
        $url_list[] = array(
            'loc' => home_url('/hokago-day-service/'),
            'lastmod' => date('Y-m-d\TH:i:s+00:00'),
            'changefreq' => 'weekly',
            'priority' => 0.9
        );
        
        // 各都道府県ページ
        foreach ($prefectures as $slug => $data) {
            $classroom_count = get_prefecture_classroom_count($slug);
            
            // 教室がある都道府県のみ高優先度、ない場合は低優先度
            $priority = ($classroom_count > 0) ? 0.8 : 0.5;
            $changefreq = ($classroom_count > 0) ? 'monthly' : 'yearly';
            
            $url_list[] = array(
                'loc' => home_url('/hokago-day-service/' . $slug . '/'),
                'lastmod' => date('Y-m-d\TH:i:s+00:00'),
                'changefreq' => $changefreq,
                'priority' => $priority
            );
        }
        
        return $url_list;
    }
    
    public function get_max_num_pages($object_subtype = '') {
        return 1; // 全ての都道府県を1ページに含める
    }
    
    public function get_sitemap_entries() {
        return $this->get_url_list(1);
    }
}

/**
 * 2. 既存のWordPressサイトマップに都道府県ページを統合
 */
function add_prefecture_to_wordpress_sitemap($sitemaps) {
    if (!function_exists('get_prefecture_mapping')) {
        return $sitemaps;
    }
    
    $prefectures = get_prefecture_mapping();
    
    // 既存のページサイトマップに都道府県ページを追加
    add_filter('wp_sitemaps_pages_entry', function($entry, $post) use ($prefectures) {
        // 通常のページエントリーはそのまま返す
        return $entry;
    }, 10, 2);
    
    return $sitemaps;
}
add_filter('wp_sitemaps_get_sitemaps', 'add_prefecture_to_wordpress_sitemap');

/**
 * 3. 独立したXMLサイトマップファイルを生成
 */
function generate_prefecture_xml_sitemap() {
    if (!function_exists('get_prefecture_mapping')) {
        return;
    }
    
    $prefectures = get_prefecture_mapping();
    
    // サイトマップXMLを生成
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    // メインページ
    $xml .= '  <url>' . "\n";
    $xml .= '    <loc>' . esc_url(home_url('/hokago-day-service/')) . '</loc>' . "\n";
    $xml .= '    <lastmod>' . date('Y-m-d\TH:i:s+00:00') . '</lastmod>' . "\n";
    $xml .= '    <changefreq>weekly</changefreq>' . "\n";
    $xml .= '    <priority>0.9</priority>' . "\n";
    $xml .= '  </url>' . "\n";
    
    // 各都道府県ページ
    foreach ($prefectures as $slug => $data) {
        $classroom_count = get_prefecture_classroom_count($slug);
        $priority = ($classroom_count > 0) ? '0.8' : '0.5';
        $changefreq = ($classroom_count > 0) ? 'monthly' : 'yearly';
        
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . esc_url(home_url('/hokago-day-service/' . $slug . '/')) . '</loc>' . "\n";
        $xml .= '    <lastmod>' . date('Y-m-d\TH:i:s+00:00') . '</lastmod>' . "\n";
        $xml .= '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
        $xml .= '    <priority>' . $priority . '</priority>' . "\n";
        $xml .= '  </url>' . "\n";
    }
    
    $xml .= '</urlset>';
    
    return $xml;
}

/**
 * 4. 独立したサイトマップファイルの出力エンドポイント
 */
function handle_prefecture_sitemap_request() {
    // /prefecture-sitemap.xml へのリクエストを処理
    if (isset($_SERVER['REQUEST_URI']) && 
        (strpos($_SERVER['REQUEST_URI'], '/prefecture-sitemap.xml') !== false ||
         strpos($_SERVER['REQUEST_URI'], '/hokago-sitemap.xml') !== false)) {
        
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex');
        
        echo generate_prefecture_xml_sitemap();
        exit;
    }
}
add_action('init', 'handle_prefecture_sitemap_request', 1);

/**
 * 5. robots.txtにサイトマップURLを追加
 */
function add_prefecture_sitemap_to_robots($output, $public) {
    if ($public) {
        $output .= "\n# Prefecture pages sitemap\n";
        $output .= "Sitemap: " . home_url('/prefecture-sitemap.xml') . "\n";
        $output .= "Sitemap: " . home_url('/hokago-sitemap.xml') . "\n";
        $output .= "\n# Main WordPress sitemaps\n";
        $output .= "Sitemap: " . home_url('/wp-sitemap.xml') . "\n";
        $output .= "Sitemap: " . home_url('/sitemap_index.xml') . "\n";
    }
    return $output;
}
add_filter('robots_txt', 'add_prefecture_sitemap_to_robots', 10, 2);

/**
 * 6. Google Search Console向けサイトマップインデックス
 */
function generate_sitemap_index() {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    // メインWordPressサイトマップ
    $xml .= '  <sitemap>' . "\n";
    $xml .= '    <loc>' . esc_url(home_url('/wp-sitemap.xml')) . '</loc>' . "\n";
    $xml .= '    <lastmod>' . date('Y-m-d\TH:i:s+00:00') . '</lastmod>' . "\n";
    $xml .= '  </sitemap>' . "\n";
    
    // 都道府県ページサイトマップ
    $xml .= '  <sitemap>' . "\n";
    $xml .= '    <loc>' . esc_url(home_url('/prefecture-sitemap.xml')) . '</loc>' . "\n";
    $xml .= '    <lastmod>' . date('Y-m-d\TH:i:s+00:00') . '</lastmod>' . "\n";
    $xml .= '  </sitemap>' . "\n";
    
    // 求人ページサイトマップ（存在する場合）
    if (file_exists(ABSPATH . 'job-sitemap.xml')) {
        $xml .= '  <sitemap>' . "\n";
        $xml .= '    <loc>' . esc_url(home_url('/job-sitemap.xml')) . '</loc>' . "\n";
        $xml .= '    <lastmod>' . date('Y-m-d\TH:i:s+00:00') . '</lastmod>' . "\n";
        $xml .= '  </sitemap>' . "\n";
    }
    
    $xml .= '</sitemapindex>';
    
    return $xml;
}

/**
 * 7. サイトマップインデックスの出力エンドポイント
 */
function handle_sitemap_index_request() {
    if (isset($_SERVER['REQUEST_URI']) && 
        strpos($_SERVER['REQUEST_URI'], '/sitemap-index.xml') !== false) {
        
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex');
        
        echo generate_sitemap_index();
        exit;
    }
}
add_action('init', 'handle_sitemap_index_request', 1);

/**
 * 8. 管理画面にサイトマップ確認ページを追加
 */
function add_sitemap_admin_page() {
    add_options_page(
        'サイトマップ確認',
        'サイトマップ確認',
        'manage_options',
        'sitemap-checker',
        'display_sitemap_checker_page'
    );
}
add_action('admin_menu', 'add_sitemap_admin_page');

function display_sitemap_checker_page() {
    if (!current_user_can('manage_options')) {
        wp_die('権限がありません。');
    }
    
    $site_url = home_url();
    $prefectures = get_prefecture_mapping();
    
    ?>
    <div class="wrap">
        <h1>サイトマップ確認</h1>
        
        <h2>利用可能なサイトマップURL</h2>
        <ul>
            <li><a href="<?php echo $site_url; ?>/wp-sitemap.xml" target="_blank">WordPress標準サイトマップ</a></li>
            <li><a href="<?php echo $site_url; ?>/prefecture-sitemap.xml" target="_blank">都道府県ページサイトマップ</a></li>
            <li><a href="<?php echo $site_url; ?>/hokago-sitemap.xml" target="_blank">放課後デイサービスサイトマップ</a></li>
            <li><a href="<?php echo $site_url; ?>/sitemap-index.xml" target="_blank">サイトマップインデックス</a></li>
        </ul>
        
        <h2>Google Search Console登録推奨URL</h2>
        <p>以下のURLをGoogle Search Consoleに登録することをお勧めします：</p>
        <div style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin: 20px 0;">
            <strong>メインサイトマップ：</strong><br>
            <code><?php echo $site_url; ?>/sitemap-index.xml</code><br><br>
            
            <strong>個別サイトマップ（必要に応じて）：</strong><br>
            <code><?php echo $site_url; ?>/wp-sitemap.xml</code><br>
            <code><?php echo $site_url; ?>/prefecture-sitemap.xml</code>
        </div>
        
        <h2>都道府県ページ一覧（<?php echo count($prefectures); ?>ページ）</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>都道府県</th>
                    <th>URL</th>
                    <th>教室数</th>
                    <th>優先度</th>
                    <th>ステータス</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prefectures as $slug => $data): ?>
                <?php 
                $classroom_count = get_prefecture_classroom_count($slug);
                $url = home_url('/hokago-day-service/' . $slug . '/');
                $priority = ($classroom_count > 0) ? 'High (0.8)' : 'Low (0.5)';
                $status = ($classroom_count > 0) ? '✅ 教室あり' : '⚠️ 準備中';
                ?>
                <tr>
                    <td><?php echo esc_html($data['name']); ?></td>
                    <td><a href="<?php echo esc_url($url); ?>" target="_blank"><?php echo esc_html($url); ?></a></td>
                    <td><?php echo $classroom_count; ?>校</td>
                    <td><?php echo $priority; ?></td>
                    <td><?php echo $status; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h2>サイトマップ検証</h2>
        <p>以下のボタンでサイトマップの動作を確認できます：</p>
        <a href="<?php echo admin_url('admin-post.php?action=test_sitemap_generation'); ?>" 
           class="button button-secondary">サイトマップ生成テスト</a>
        <a href="<?php echo admin_url('admin-post.php?action=ping_google_sitemaps'); ?>" 
           class="button button-primary">Googleに更新通知</a>
    </div>
    <?php
}

/**
 * 9. サイトマップ生成テスト機能
 */
function test_sitemap_generation() {
    if (!current_user_can('manage_options')) {
        wp_die('権限がありません。');
    }
    
    $results = array();
    
    // 都道府県サイトマップのテスト
    $prefecture_xml = generate_prefecture_xml_sitemap();
    $results['prefecture'] = array(
        'status' => !empty($prefecture_xml),
        'size' => strlen($prefecture_xml),
        'urls' => substr_count($prefecture_xml, '<url>')
    );
    
    // サイトマップインデックスのテスト
    $index_xml = generate_sitemap_index();
    $results['index'] = array(
        'status' => !empty($index_xml),
        'size' => strlen($index_xml),
        'sitemaps' => substr_count($index_xml, '<sitemap>')
    );
    
    // 結果を表示
    wp_redirect(admin_url('options-general.php?page=sitemap-checker&test=completed&results=' . urlencode(json_encode($results))));
    exit;
}
add_action('admin_post_test_sitemap_generation', 'test_sitemap_generation');

/**
 * 10. Googleへのサイトマップ更新通知
 */
function ping_google_sitemaps_manual() {
    if (!current_user_can('manage_options')) {
        wp_die('権限がありません。');
    }
    
    $sitemap_urls = array(
        home_url('/sitemap-index.xml'),
        home_url('/wp-sitemap.xml'),
        home_url('/prefecture-sitemap.xml')
    );
    
    $ping_results = array();
    
    foreach ($sitemap_urls as $sitemap_url) {
        $ping_url = 'https://www.google.com/ping?sitemap=' . urlencode($sitemap_url);
        $response = wp_remote_get($ping_url, array('timeout' => 30));
        
        $ping_results[] = array(
            'sitemap' => $sitemap_url,
            'status' => !is_wp_error($response) ? wp_remote_response_code($response) : 'Error',
            'message' => is_wp_error($response) ? $response->get_error_message() : 'Success'
        );
    }
    
    wp_redirect(admin_url('options-general.php?page=sitemap-checker&ping=completed&results=' . urlencode(json_encode($ping_results))));
    exit;
}
add_action('admin_post_ping_google_sitemaps', 'ping_google_sitemaps_manual');

/**
 * 11. 自動サイトマップ更新（投稿更新時）
 */
function auto_update_sitemaps_on_content_change($post_id) {
    // 求人投稿またはページが更新された時にサイトマップを更新
    if (get_post_type($post_id) === 'job' || get_post_type($post_id) === 'page') {
        
        // 遅延実行でGoogleに通知（5分後）
        wp_schedule_single_event(time() + 300, 'ping_google_sitemap_update');
        
        // サイトマップキャッシュクリア（各種SEOプラグイン対応）
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Yoast SEO
        if (class_exists('WPSEO_Sitemaps')) {
            WPSEO_Sitemaps::invalidate_cache();
        }
        
        // RankMath
        if (class_exists('RankMath')) {
            \RankMath\Sitemap\Cache::invalidate();
        }
        
        // WordPress標準サイトマップキャッシュクリア
        wp_cache_delete('sitemaps', 'core');
    }
}
add_action('save_post', 'auto_update_sitemaps_on_content_change');

/**
 * 12. サイトマップのキャッシュ制御
 */
function control_sitemap_caching() {
    if (strpos($_SERVER['REQUEST_URI'] ?? '', 'sitemap') !== false) {
        // サイトマップは1時間キャッシュ
        header('Cache-Control: public, max-age=3600');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
    }
}
add_action('init', 'control_sitemap_caching', 1);

/**
 * 13. サイトマップ統計情報の記録
 */
function track_sitemap_access() {
    if (strpos($_SERVER['REQUEST_URI'] ?? '', 'sitemap') !== false) {
        $access_log = get_option('sitemap_access_log', array());
        $today = date('Y-m-d');
        
        if (!isset($access_log[$today])) {
            $access_log[$today] = 0;
        }
        
        $access_log[$today]++;
        
        // 過去30日分のみ保持
        $cutoff_date = date('Y-m-d', strtotime('-30 days'));
        foreach ($access_log as $date => $count) {
            if ($date < $cutoff_date) {
                unset($access_log[$date]);
            }
        }
        
        update_option('sitemap_access_log', $access_log);
    }
}
add_action('init', 'track_sitemap_access', 2);

/**
 * 14. デバッグ用：サイトマップ内容確認
 */
function debug_sitemap_content() {
    if (current_user_can('administrator') && isset($_GET['debug_sitemap'])) {
        echo '<pre>';
        echo "Prefecture Sitemap:\n";
        echo "==================\n";
        echo generate_prefecture_xml_sitemap();
        echo "\n\nSitemap Index:\n";
        echo "==============\n";
        echo generate_sitemap_index();
        echo '</pre>';
        exit;
    }
}
add_action('init', 'debug_sitemap_content', 999);

/**
 * 15. 初期化時の処理
 */
function initialize_prefecture_sitemaps() {
    // リライトルールが設定されているか確認
    $rules_option = get_option('prefecture_sitemap_initialized');
    
    if (!$rules_option) {
        // 必要な権限とオプションを設定
        flush_rewrite_rules();
        update_option('prefecture_sitemap_initialized', true);
        
        // 初回のサイトマップ生成とGoogle通知
        wp_schedule_single_event(time() + 60, 'ping_google_sitemap_update');
    }
}
add_action('init', 'initialize_prefecture_sitemaps', 999);

/**
 * 16. WP-CLI対応（コマンドライン実行）
 */
if (defined('WP_CLI') && WP_CLI) {
    
    WP_CLI::add_command('prefecture-sitemap', function($args, $assoc_args) {
        $action = $args[0] ?? 'generate';
        
        switch ($action) {
            case 'generate':
                $xml = generate_prefecture_xml_sitemap();
                WP_CLI::success('Prefecture sitemap generated (' . strlen($xml) . ' bytes)');
                break;
                
            case 'ping':
                $sitemap_urls = array(
                    home_url('/sitemap-index.xml'),
                    home_url('/prefecture-sitemap.xml')
                );
                
                foreach ($sitemap_urls as $url) {
                    $ping_url = 'https://www.google.com/ping?sitemap=' . urlencode($url);
                    $response = wp_remote_get($ping_url);
                    
                    if (!is_wp_error($response)) {
                        WP_CLI::success('Pinged: ' . $url);
                    } else {
                        WP_CLI::error('Failed to ping: ' . $url);
                    }
                }
                break;
                
            case 'check':
                $prefectures = get_prefecture_mapping();
                WP_CLI::line('Prefecture pages: ' . count($prefectures));
                
                $with_classrooms = 0;
                foreach ($prefectures as $slug => $data) {
                    $count = get_prefecture_classroom_count($slug);
                    if ($count > 0) {
                        $with_classrooms++;
                    }
                }
                
                WP_CLI::line('Pages with classrooms: ' . $with_classrooms);
                WP_CLI::line('Pages without classrooms: ' . (count($prefectures) - $with_classrooms));
                break;
                
            default:
                WP_CLI::error('Unknown action. Available: generate, ping, check');
        }
    });
}




/**
 * 都道府県ページのメタタグ強化
 */
function enhance_prefecture_meta_tags() {
    $prefecture_slug = get_query_var('prefecture');
    
    if ($prefecture_slug) {
        // get_prefecture_mapping関数が存在するかチェック
        if (function_exists('get_prefecture_mapping')) {
            $prefectures = get_prefecture_mapping();
            
            if (isset($prefectures[$prefecture_slug])) {
                $pref_name = $prefectures[$prefecture_slug]['name'];
                
                add_action('wp_head', function() use ($pref_name) {
                    // 追加のメタタグ
                    echo '<meta name="keywords" content="放課後等デイサービス,児童発達支援,' . esc_attr($pref_name) . ',こどもプラス,療育,運動あそび,発達障害">' . "\n";
                    echo '<meta name="author" content="こどもプラス">' . "\n";
                    echo '<meta name="DC.title" content="' . esc_attr($pref_name) . 'の放課後等デイサービスの事業所の一覧">' . "\n";
                    echo '<meta name="DC.subject" content="放課後等デイサービス ' . esc_attr($pref_name) . '">' . "\n";
                    echo '<meta name="DC.description" content="' . esc_attr($pref_name) . 'の放課後等デイサービス・児童発達支援の事業所をご紹介">' . "\n";
                    
                    // Twitter Cards
                    echo '<meta name="twitter:card" content="summary">' . "\n";
                    echo '<meta name="twitter:title" content="' . esc_attr($pref_name) . 'の放課後等デイサービスの事業所の一覧">' . "\n";
                    echo '<meta name="twitter:description" content="' . esc_attr($pref_name) . 'のこどもプラス教室をご紹介。放課後等デイサービス・児童発達支援事業所の詳細情報をご確認いただけます。">' . "\n";
                }, 5);
            }
        }
    }
}
add_action('wp', 'enhance_prefecture_meta_tags');

/**
 * 都道府県ページの正規化（重複コンテンツ対策）
 */
function prefecture_canonical_redirect() {
    // 古いURLパターン（?pref=XX）から新しいURLにリダイレクト
    if (isset($_GET['pref']) && is_numeric($_GET['pref'])) {
        $pref_id = intval($_GET['pref']);
        
        if (function_exists('get_prefecture_mapping')) {
            $prefectures = get_prefecture_mapping();
            
            foreach ($prefectures as $slug => $data) {
                if (isset($data['id']) && $data['id'] == $pref_id) {
                    wp_redirect(home_url('/hokago-day-service/' . $slug . '/'), 301);
                    exit;
                }
            }
        }
    }
}
add_action('template_redirect', 'prefecture_canonical_redirect');

/**
 * 都道府県ページの読み込み速度最適化
 */
function optimize_prefecture_page_speed() {
    $prefecture_slug = get_query_var('prefecture');
    
    if ($prefecture_slug || is_page('list')) {
        // 重要でないスクリプトを遅延読み込み
        add_action('wp_enqueue_scripts', function() {
            wp_dequeue_script('wp-embed');
        }, 100);
        
        // プリロードヒントを追加
        add_action('wp_head', function() {
            echo '<link rel="preload" href="' . get_stylesheet_directory_uri() . '/img/map.svg" as="image">' . "\n";
        }, 1);
    }
}
add_action('wp', 'optimize_prefecture_page_speed');

/**
 * 都道府県ページのAMP対応（オプション）
 */
function add_prefecture_amp_support() {
    if (function_exists('amp_is_request') && amp_is_request()) {
        $prefecture_slug = get_query_var('prefecture');
        
        if ($prefecture_slug && function_exists('get_prefecture_mapping')) {
            add_filter('amp_post_template_data', function($data) use ($prefecture_slug) {
                $prefectures = get_prefecture_mapping();
                if (isset($prefectures[$prefecture_slug])) {
                    $pref_name = $prefectures[$prefecture_slug]['name'];
                    $data['post_title'] = $pref_name . 'の放課後等デイサービスの事業所の一覧';
                    $data['post_author'] = 'こどもプラス';
                }
                return $data;
            });
        }
    }
}
add_action('wp', 'add_prefecture_amp_support');

/**
 * 検索エンジン向けの追加情報
 */
function add_prefecture_search_engine_hints() {
    $prefecture_slug = get_query_var('prefecture');
    
    if ($prefecture_slug && function_exists('get_prefecture_mapping')) {
        $prefectures = get_prefecture_mapping();
        
        if (isset($prefectures[$prefecture_slug])) {
            $pref_name = $prefectures[$prefecture_slug]['name'];
            
            add_action('wp_head', function() use ($pref_name) {
                // 検索エンジン向けのヒント
                echo '<meta name="subject" content="' . esc_attr($pref_name) . 'の放課後等デイサービス">' . "\n";
                echo '<meta name="topic" content="児童発達支援">' . "\n";
                echo '<meta name="summary" content="' . esc_attr($pref_name) . 'にあるこどもプラスの事業所の一覧">' . "\n";
                echo '<meta name="classification" content="Education, Childcare">' . "\n";
                echo '<meta name="coverage" content="' . esc_attr($pref_name) . '">' . "\n";
                echo '<meta name="distribution" content="Global">' . "\n";
                echo '<meta name="rating" content="General">' . "\n";
            }, 3);
        }
    }
}
add_action('wp', 'add_prefecture_search_engine_hints');

/**
 * 都道府県別テーブルフィルタリング機能
 */
function filter_prefecture_table_data($query) {
    $prefecture_slug = get_query_var('prefecture');
    
    if ($prefecture_slug && function_exists('get_prefecture_mapping')) {
        $prefectures = get_prefecture_mapping();
        
        if (isset($prefectures[$prefecture_slug])) {
            // 特定の都道府県のデータのみを表示するためのフィルター
            add_filter('pre_get_posts', function($query) use ($prefecture_slug) {
                if (!is_admin() && $query->is_main_query()) {
                    // 都道府県固有のフィルタリングロジックをここに追加
                    $query->set('meta_query', array(
                        array(
                            'key' => 'prefecture_slug',
                            'value' => $prefecture_slug,
                            'compare' => '='
                        )
                    ));
                }
                return $query;
            });
        }
    }
}
add_action('wp', 'filter_prefecture_table_data');

/**
 * インタラクティブな都道府県地図機能
 * 他の都道府県もクリックで選択可能にする
 */
function add_interactive_prefecture_map() {
    // 教室一覧ページまたは都道府県ページで実行
    if (is_page('list') || get_query_var('prefecture')) {
        add_action('wp_footer', function() {
            $current_prefecture = get_query_var('prefecture');
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var currentPrefSlug = '<?php echo esc_js($current_prefecture ?: ''); ?>';
                var baseUrl = '<?php echo home_url('/hokago-day-service/'); ?>';
                
                // 都道府県マッピング
                var prefectureMapping = {
                    'hokkaido': {id: '1', name: '北海道'},
                    'aomori': {id: '2', name: '青森県'},
                    'iwate': {id: '3', name: '岩手県'},
                    'miyagi': {id: '4', name: '宮城県'},
                    'akita': {id: '5', name: '秋田県'},
                    'yamagata': {id: '6', name: '山形県'},
                    'fukushima': {id: '7', name: '福島県'},
                    'ibaraki': {id: '8', name: '茨城県'},
                    'tochigi': {id: '9', name: '栃木県'},
                    'gunma': {id: '10', name: '群馬県'},
                    'saitama': {id: '11', name: '埼玉県'},
                    'chiba': {id: '12', name: '千葉県'},
                    'tokyo': {id: '13', name: '東京都'},
                    'kanagawa': {id: '14', name: '神奈川県'},
                    'niigata': {id: '15', name: '新潟県'},
                    'toyama': {id: '16', name: '富山県'},
                    'ishikawa': {id: '17', name: '石川県'},
                    'fukui': {id: '18', name: '福井県'},
                    'yamanashi': {id: '19', name: '山梨県'},
                    'nagano': {id: '20', name: '長野県'},
                    'gifu': {id: '21', name: '岐阜県'},
                    'shizuoka': {id: '22', name: '静岡県'},
                    'aichi': {id: '23', name: '愛知県'},
                    'mie': {id: '24', name: '三重県'},
                    'shiga': {id: '25', name: '滋賀県'},
                    'kyoto': {id: '26', name: '京都府'},
                    'osaka': {id: '27', name: '大阪府'},
                    'hyogo': {id: '28', name: '兵庫県'},
                    'nara': {id: '29', name: '奈良県'},
                    'wakayama': {id: '30', name: '和歌山県'},
                    'tottori': {id: '31', name: '鳥取県'},
                    'shimane': {id: '32', name: '島根県'},
                    'okayama': {id: '33', name: '岡山県'},
                    'hiroshima': {id: '34', name: '広島県'},
                    'yamaguchi': {id: '35', name: '山口県'},
                    'tokushima': {id: '36', name: '徳島県'},
                    'kagawa': {id: '37', name: '香川県'},
                    'ehime': {id: '38', name: '愛媛県'},
                    'kochi': {id: '39', name: '高知県'},
                    'fukuoka': {id: '40', name: '福岡県'},
                    'saga': {id: '41', name: '佐賀県'},
                    'nagasaki': {id: '42', name: '長崎県'},
                    'kumamoto': {id: '43', name: '熊本県'},
                    'oita': {id: '44', name: '大分県'},
                    'miyazaki': {id: '45', name: '宮崎県'},
                    'kagoshima': {id: '46', name: '鹿児島県'},
                    'okinawa': {id: '47', name: '沖縄県'}
                };
                
                // IDから都道府県スラッグを取得する関数
                function getSlugById(id) {
                    for (var slug in prefectureMapping) {
                        if (prefectureMapping[slug].id === String(id)) {
                            return slug;
                        }
                    }
                    return null;
                }
                
                // 都道府県名から都道府県スラッグを取得する関数
                function getSlugByName(name) {
                    for (var slug in prefectureMapping) {
                        if (prefectureMapping[slug].name === name) {
                            return slug;
                        }
                    }
                    return null;
                }
                
                // 地図要素を取得して設定
                function setupInteractiveMap() {
                    var mapElements = document.querySelectorAll('svg path, svg g, .prefecture-area, .map-area, [data-id], [data-prefecture], area');
                    
                    mapElements.forEach(function(element) {
                        var prefSlug = null;
                        var prefName = null;
                        
                        // 要素から都道府県を特定
                        var dataId = element.getAttribute('data-id');
                        var dataPrefecture = element.getAttribute('data-prefecture');
                        var title = element.getAttribute('title');
                        var elementText = element.textContent;
                        var elementId = element.id;
                        
                        // 各属性から都道府県スラッグを特定
                        if (dataId) {
                            prefSlug = getSlugById(dataId);
                        } else if (dataPrefecture) {
                            prefSlug = getSlugByName(dataPrefecture) || dataPrefecture;
                        } else if (title) {
                            prefSlug = getSlugByName(title);
                        } else if (elementText) {
                            prefSlug = getSlugByName(elementText.trim());
                        } else if (elementId && prefectureMapping[elementId]) {
                            prefSlug = elementId;
                        }
                        
                        if (prefSlug && prefectureMapping[prefSlug]) {
                            prefName = prefectureMapping[prefSlug].name;
                            
                            // 現在のページの都道府県かチェック
                            var isCurrentPref = (prefSlug === currentPrefSlug);
                            
                            // スタイルをリセット
                            element.style.cursor = 'pointer';
                            element.classList.remove('selected', 'active', 'current');
                            
                            // 現在の都道府県には選択状態のスタイルを適用
                            if (isCurrentPref) {
                                element.classList.add('selected', 'current');
                                element.style.fill = '#ff6b6b';
                                element.style.stroke = '#ff4757';
                                element.style.strokeWidth = '2px';
                            } else {
                                // 他の都道府県にはホバーエフェクトを設定
                                element.style.fill = element.style.fill || '#e0e0e0';
                                element.style.transition = 'all 0.3s ease';
                                
                                element.addEventListener('mouseenter', function() {
                                    if (!this.classList.contains('selected')) {
                                        this.style.fill = '#a8dadc';
                                        this.style.stroke = '#457b9d';
                                        this.style.strokeWidth = '1px';
                                    }
                                });
                                
                                element.addEventListener('mouseleave', function() {
                                    if (!this.classList.contains('selected')) {
                                        this.style.fill = '#e0e0e0';
                                        this.style.stroke = '';
                                        this.style.strokeWidth = '';
                                    }
                                });
                            }
                            
                            // クリックイベントを追加
element.addEventListener('click', function(e) {
    // クリックされたのが<a>タグ、または<a>タグの子要素であれば、処理を中断してリンクを機能させる
    if (e.target.closest('a')) {
        return;
    }
    
    // リンクでない場合のみ、地図の動作（デフォルト動作のキャンセルとページ遷移）を実行
    e.preventDefault();
    
    // 選択状態を更新
    mapElements.forEach(function(el) {
        el.classList.remove('selected', 'current');
        if (!el.classList.contains('selected')) {
            el.style.fill = '#e0e0e0';
            el.style.stroke = '';
            el.style.strokeWidth = '';
        }
    });
    
    this.classList.add('selected', 'current');
    this.style.fill = '#ff6b6b';
    this.style.stroke = '#ff4757';
    this.style.strokeWidth = '2px';
    
    // ページ遷移
    var targetUrl = baseUrl + prefSlug + '/';
    window.location.href = targetUrl;
});
                            
                            // ツールチップを追加
                            element.setAttribute('title', prefName + 'の教室一覧を見る');
                        }
                    });
                }
                
                // 現在の都道府県のデータをフィルタリング
                function filterCurrentPrefectureData() {
                    if (!currentPrefSlug || !prefectureMapping[currentPrefSlug]) {
                        return; // メインページの場合は何もしない
                    }
                    
                    var prefId = prefectureMapping[currentPrefSlug].id;
                    var prefName = prefectureMapping[currentPrefSlug].name;
                    
                    // 既存のメッセージをクリア
                    var existingMessages = document.querySelectorAll('.prefecture-filter-info, .prefecture-no-data');
                    existingMessages.forEach(function(msg) { msg.remove(); });
                    
                    // area_overlayを非表示にして地図を操作可能にする
                    var overlays = document.querySelectorAll('.area_overlay');
                    overlays.forEach(function(overlay) {
                        overlay.style.display = 'none';
                    });
                    
                    // pref_list内の都道府県要素は表示状態にする（地図操作のため）
                    var prefListElements = document.querySelectorAll('.pref_list [data-id]');
                    prefListElements.forEach(function(element) {
                        element.style.display = 'block';
                    });
                    
                    // data-id属性でフィルタリング（地図要素とpref_list内要素は除外）
                    var allElements = document.querySelectorAll('[data-id]:not(svg):not(svg *):not(.prefecture-area):not(.map-area):not(.pref_list [data-id])');
                    var visibleCount = 0;
                    
                    allElements.forEach(function(element) {
                        // 地図関連の要素かチェック
                        var isMapElement = element.closest('svg') || 
                                          element.classList.contains('prefecture-area') || 
                                          element.classList.contains('map-area') ||
                                          element.closest('.pref_list') ||
                                          element.tagName === 'path' ||
                                          element.tagName === 'g' ||
                                          element.tagName === 'area';
                        
                        if (!isMapElement) {
                            var dataId = element.getAttribute('data-id');
                            if (dataId === prefId) {
                                element.style.display = '';
                                visibleCount++;
                            } else {
                                element.style.display = 'none';
                            }
                        }
                    });
                    
                    // テーブルフィルタリング
                    var tables = document.querySelectorAll('.table, table');
                    var tableVisibleRows = 0;
                    
                    tables.forEach(function(table) {
                        var rows = table.querySelectorAll('tbody tr, tr');
                        
                        rows.forEach(function(row) {
                            var cells = row.querySelectorAll('td, th');
                            var shouldShow = false;
                            
                            // ヘッダー行は常に表示
                            if (row.querySelector('th') || row.classList.contains('header')) {
                                shouldShow = true;
                            } else {
                                cells.forEach(function(cell) {
                                    var cellText = cell.textContent || cell.innerText;
                                    if (cellText.includes(prefName)) {
                                        shouldShow = true;
                                    }
                                });
                            }
                            
                            if (shouldShow) {
                                row.style.display = '';
                                if (!row.querySelector('th')) tableVisibleRows++;
                            } else {
                                row.style.display = 'none';
                            }
                        });
                    });
                    
                    // メッセージ表示
                    var messageContainer = document.querySelector('.content, main, .main-content, body');
                    if (messageContainer) {
                        if (visibleCount > 0 || tableVisibleRows > 0) {
                            var msg = document.createElement('div');
                            msg.className = 'prefecture-filter-info';
                            msg.innerHTML = '<strong>' + prefName + 'の放課後等デイサービスの事業所</strong>' + 
                                           (tableVisibleRows > 0 ? '（' + tableVisibleRows + '件）' : '');
                            msg.style.cssText = 'margin:20px 0 15px 0;padding:15px;background:#f0f8ff;border-left:4px solid #0073aa;border-radius:4px;font-size:16px;color:#333;box-shadow:0 2px 4px rgba(0,0,0,0.1);';
                            
                            var firstElement = document.querySelector('[data-id="' + prefId + '"], table') || messageContainer.firstElementChild;
                            if (firstElement) {
                                messageContainer.insertBefore(msg, firstElement);
                            }
                        } else {
                            var noDataMsg = document.createElement('div');
                            noDataMsg.className = 'prefecture-no-data';
                            noDataMsg.innerHTML = '<div style="text-align:center;padding:40px 20px;background:#f9f9f9;border:1px solid #ddd;border-radius:8px;margin:20px 0;"><h3 style="color:#666;margin-bottom:10px;">' + prefName + 'の教室情報</h3><p style="color:#888;">現在準備中です。詳細についてはお問い合わせください。</p></div>';
                            messageContainer.appendChild(noDataMsg);
                        }
                    }
                    
                    // ページタイトル更新
                    var titleElement = document.querySelector('h1, .page-title, .entry-title');
                    if (titleElement && !titleElement.textContent.includes(prefName)) {
                        titleElement.textContent = prefName + 'の放課後等デイサービスの事業所の一覧';
                    }
                }
                
                // 実行
                setupInteractiveMap();
                filterCurrentPrefectureData();
                
                // 動的コンテンツ対応
                setTimeout(function() {
                    setupInteractiveMap();
                    filterCurrentPrefectureData();
                }, 500);
            });
            </script>
            <?php
        });
    }
}

// インタラクティブマップ機能を追加
add_action('wp', 'add_interactive_prefecture_map');



/**
 * 都道府県別SEO最適化コード（改善版・絵文字なし・県名強化）
 */

/**
 * 1. 都道府県ページのメタタグとタイトル強化
 */
function enhanced_prefecture_seo() {
    $prefecture_slug = get_query_var('prefecture');
    
    if ($prefecture_slug) {
        $prefectures = get_prefecture_mapping();
        
        if (isset($prefectures[$prefecture_slug])) {
            $pref_name = $prefectures[$prefecture_slug]['name'];
            $site_name = get_bloginfo('name');
            
            // 教室数を実際のデータから取得
            $classroom_count = get_prefecture_classroom_count($prefecture_slug);
            
            // タイトルタグの最適化（県名を複数回使用）
            $title = "{$pref_name}の放課後等デイサービスの事業所の一覧【{$classroom_count}校】| {$pref_name}で運動療育なら{$site_name}";
            
            // メタディスクリプションの最適化（県名を自然に3-4回含める）
            $description = "{$pref_name}で放課後等デイサービスをお探しなら運動療育専門の{$site_name}へ。{$pref_name}県内{$classroom_count}校の事業所で発達障害・ADHDのお子さまを支援。{$pref_name}各地域の住所・連絡先・求人情報を掲載。児童発達支援も対応。";
            
            // 構造化データの強化
            add_action('wp_head', function() use ($title, $description, $prefecture_slug, $pref_name, $classroom_count, $site_name) {
                echo '<title>' . esc_html($title) . '</title>' . "\n";
                echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
                
                // 地域特化キーワード（県名を含む）
                $keywords = "{$pref_name},放課後等デイサービス,{$pref_name} 児童発達支援,{$pref_name} 運動療育,{$pref_name} 発達障害,{$pref_name} ADHD,{$pref_name} こどもプラス,{$pref_name} 療育,運動遊び,児童デイサービス,放デイ";
                echo '<meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
                
                // 地域情報メタタグ
                echo '<meta name="geo.region" content="JP-' . get_prefecture_code($pref_name) . '">' . "\n";
                echo '<meta name="geo.placename" content="' . esc_attr($pref_name) . '">' . "\n";
                echo '<meta name="ICBM" content="' . get_prefecture_coordinates($pref_name) . '">' . "\n";
                
                // Open Graph強化
                echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
                echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
                echo '<meta property="og:type" content="website">' . "\n";
                echo '<meta property="og:locale" content="ja_JP">' . "\n";
                echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";
                
                // Twitter Card
                echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
                echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
                echo '<meta name="twitter:description" content="' . esc_attr(mb_substr($description, 0, 200)) . '">' . "\n";
                
                // 構造化データ（LocalBusiness）
                $structured_data = array(
                    '@context' => 'https://schema.org',
                    '@type' => 'WebPage',
                    'name' => $title,
                    'description' => $description,
                    'url' => home_url('/hokago-day-service/' . $prefecture_slug . '/'),
                    'about' => array(
                        '@type' => 'Service',
                        'name' => "{$pref_name}の放課後等デイサービス",
                        'description' => "{$pref_name}県内の放課後等デイサービス・児童発達支援の事業所",
                        'serviceType' => '放課後等デイサービス',
                        'provider' => array(
                            '@type' => 'Organization',
                            'name' => 'こどもプラス',
                            'url' => home_url()
                        ),
                        'areaServed' => array(
                            '@type' => 'State',
                            'name' => $pref_name
                        )
                    ),
                    'breadcrumb' => array(
                        '@type' => 'BreadcrumbList',
                        'itemListElement' => array(
                            array(
                                '@type' => 'ListItem',
                                'position' => 1,
                                'name' => 'ホーム',
                                'item' => home_url()
                            ),
                            array(
                                '@type' => 'ListItem',
                                'position' => 2,
                                'name' => '放課後等デイサービスの事業所の一覧',
                                'item' => home_url('/hokago-day-service/')
                            ),
                            array(
                                '@type' => 'ListItem',
                                'position' => 3,
                                'name' => "{$pref_name}の事業所の一覧",
                                'item' => home_url('/hokago-day-service/' . $prefecture_slug . '/')
                            )
                        )
                    ),
                    'mainEntity' => array(
                        '@type' => 'ItemList',
                        'name' => "{$pref_name}の放課後等デイサービスの事業所",
                        'description' => "{$pref_name}県内で運動療育を行う放課後等デイサービスの事業所の一覧",
                        'numberOfItems' => $classroom_count,
                        'itemListOrder' => 'https://schema.org/ItemListOrderAscending'
                    )
                );
                
                echo '<script type="application/ld+json">';
                echo json_encode($structured_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                echo '</script>' . "\n";
                
                // 各教室の構造化データ
                add_classroom_structured_data($prefecture_slug, $pref_name);
                
            }, 1);
        }
    }
}
add_action('wp', 'enhanced_prefecture_seo');

/**
 * 2. 各教室の構造化データを追加（県名強化版）
 */
function add_classroom_structured_data($prefecture_slug, $pref_name) {
    $classrooms = get_prefecture_classrooms($prefecture_slug);
    
    if (!empty($classrooms)) {
        $local_businesses = array();
        
        foreach ($classrooms as $classroom) {
            $local_businesses[] = array(
                '@type' => 'LocalBusiness',
                'name' => $classroom['name'],
                'description' => "{$pref_name}で運動療育を中心とした放課後等デイサービス・児童発達支援を提供",
                'url' => $classroom['url'],
                'telephone' => $classroom['phone'] ?? '',
                'address' => array(
                    '@type' => 'PostalAddress',
                    'streetAddress' => $classroom['address'],
                    'addressLocality' => $classroom['city'] ?? '',
                    'addressRegion' => $pref_name,
                    'addressCountry' => 'JP'
                ),
                'geo' => array(
                    '@type' => 'GeoCoordinates',
                    'latitude' => $classroom['lat'] ?? '',
                    'longitude' => $classroom['lng'] ?? ''
                ),
                'openingHours' => 'Mo-Fr 10:00-18:00',
                'priceRange' => '$$',
                'serviceArea' => array(
                    '@type' => 'GeoCircle',
                    'geoMidpoint' => array(
                        '@type' => 'GeoCoordinates',
                        'latitude' => $classroom['lat'] ?? '',
                        'longitude' => $classroom['lng'] ?? ''
                    ),
                    'geoRadius' => '10000'
                )
            );
        }
        
        if (!empty($local_businesses)) {
            echo '<script type="application/ld+json">';
            echo json_encode(array(
                '@context' => 'https://schema.org',
                '@graph' => $local_businesses
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            echo '</script>' . "\n";
        }
    }
}

/**
 * 3. 都道府県ページのコンテンツ強化（絵文字なし・県名強化版）
 */
function add_prefecture_rich_content() {
    $prefecture_slug = get_query_var('prefecture');
    
    if ($prefecture_slug) {
        $prefectures = get_prefecture_mapping();
        
        if (isset($prefectures[$prefecture_slug])) {
            $pref_name = $prefectures[$prefecture_slug]['name'];
            $classroom_count = get_prefecture_classroom_count($prefecture_slug);
            $site_name = get_bloginfo('name');
            
            // 地図の下、テーブルの上にコンテンツを挿入
            add_action('wp_footer', function() use ($pref_name, $classroom_count, $site_name) {
                ?>
                <script>
                jQuery(document).ready(function($) {
                    // アコーディオンのCSS
                    $('<style>').text(`
                        .prefecture-accordion {
                            background: #f8f9fa;
                            border-radius: 8px;
                            margin: 20px 0;
                            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                            overflow: hidden;
                        }
                        .accordion-header {
                            background: linear-gradient(135deg, #007cba, #005a8b);
                            color: white;
                            padding: 15px 20px;
                            cursor: pointer;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            transition: all 0.3s ease;
                            font-weight: 600;
                            font-size: 16px;
                        }
                        .accordion-header:hover {
                            background: linear-gradient(135deg, #0056a3, #003d6b);
                        }
                        .accordion-icon {
                            transition: transform 0.3s ease;
                            font-size: 18px;
                        }
                        .accordion-header.active .accordion-icon {
                            transform: rotate(180deg);
                        }
                        .accordion-content {
                            display: none;
                            padding: 20px;
                            background: white;
                            animation: fadeIn 0.3s ease;
                        }
                        @keyframes fadeIn {
                            from { opacity: 0; transform: translateY(-10px); }
                            to { opacity: 1; transform: translateY(0); }
                        }
                        .accordion-grid {
                            display: grid;
                            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                            gap: 15px;
                            margin: 15px 0;
                        }
                        .accordion-card {
                            padding: 15px;
                            border-radius: 6px;
                            border-left: 3px solid #007cba;
                            background: #f8f9fa;
                            transition: transform 0.2s ease;
                        }
                        .accordion-card:hover {
                            transform: translateY(-2px);
                            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                        }
                        .accordion-card h4 {
                            color: #007cba;
                            margin: 0 0 8px 0;
                            font-size: 14px;
                            font-weight: 600;
                        }
                        .accordion-card p {
                            margin: 0;
                            font-size: 12px;
                            color: #666;
                            line-height: 1.4;
                        }
                        .recruitment-highlight {
                            background: linear-gradient(135deg, #28a745, #20c997);
                            color: white;
                            padding: 12px 15px;
                            border-radius: 6px;
                            text-align: center;
                            margin-top: 15px;
                            font-size: 13px;
                            line-height: 1.4;
                        }
                        .feature-tags {
                            display: grid; 
                            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); 
                            gap: 10px; 
                            margin-top: 15px; 
                            font-size: 12px;
                        }
                        .feature-tag {
                            text-align: center; 
                            padding: 8px; 
                            border-radius: 4px;
                            font-weight: 600;
                        }
                        @media (max-width: 768px) {
                            .accordion-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
                            .accordion-content { padding: 15px; }
                            .accordion-header { padding: 12px 15px; font-size: 14px; }
                        }
                    `).appendTo('head');
                    
                    // 県名を含むアコーディオンコンテンツ（説明文を上部に移動）
                    var prefectureName = <?php echo json_encode($pref_name); ?>;
                    var classroomCount = <?php echo $classroom_count; ?>;
                    
                    // 説明文を先に作成
                    var prefectureDescription = `
                        <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); 
                                    padding: 30px 20px; 
                                    margin: 20px 0; 
                                    border-radius: 8px;
                                    border-left: 4px solid #007cba;">
                            <p style="font-size: 14px; 
                                      color: #333; 
                                      line-height: 1.8; 
                                      margin: 0 0 15px 0;">
                                <strong>${prefectureName}で放課後等デイサービス・児童発達支援をお探しの方へ</strong><br>
                                ${prefectureName}内${classroomCount}校のこどもプラス教室では、
                                運動療育を中心とした発達障害・ADHDのお子さまへの専門的な支援を行っています。
                                ${prefectureName}各地域の教室情報、アクセス、求人情報をご確認いただけます。
                            </p>
                            <p style="font-size: 14px; 
          color: #666; 
          margin: 0;">
    ※ ${prefectureName}の放課後等デイサービスでの求人をご希望の方は、
    <a href="https://recruitment.kodomo-plus.co.jp/contact/" 
       style="color: #0073e6; text-decoration: underline;">
       お問い合わせください
    </a>。
</p>

                        </div>
                    `;
                    
                    var compactContent = `
                        <div class="prefecture-accordion">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <span>${prefectureName}の求人情報（${classroomCount}校で募集中）</span>
                                <span class="accordion-icon">▼</span>
                            </div>
                            <div class="accordion-content">
                                <div class="accordion-grid">
                                    <div class="accordion-card">
                                        <h4>保育士募集</h4>
                                        <p>${prefectureName}で保育士資格をお持ちの方<br>療育分野未経験歓迎</p>
                                    </div>
                                    <div class="accordion-card">
                                        <h4>児童指導員募集</h4>
                                        <p>${prefectureName}で教員免許・社会福祉士等<br>の資格をお持ちの方</p>
                                    </div>
                                    <div class="accordion-card">
                                        <h4>運動指導員募集</h4>
                                        <p>${prefectureName}で体育・運動指導経験者<br>運動療育に興味のある方</p>
                                    </div>
                                    <div class="accordion-card">
                                        <h4>管理責任者募集</h4>
                                        <p>${prefectureName}で児童発達支援管理責任者<br>資格・経験をお持ちの方</p>
                                    </div>
                                </div>
                                <div class="recruitment-highlight">
                                    ${prefectureName}で運動療育の専門スキルを身につけて、お子さまの成長をサポートしませんか<br>
                                    詳しい求人情報は下記一覧表の「求人はこちら」をクリック
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // アコーディオンのトグル機能をグローバルに定義
                    window.toggleAccordion = function(header) {
                        var $header = $(header);
                        var $content = $header.next('.accordion-content');
                        var $icon = $header.find('.accordion-icon');
                        
                        if ($content.is(':visible')) {
                            $content.slideUp(300);
                            $header.removeClass('active');
                        } else {
                            $content.slideDown(300);
                            $header.addClass('active');
                        }
                    };
                    
                    // .japan_map の直後に説明文とアコーディオンを挿入
                    $('.japan_map').after(prefectureDescription + compactContent);
                });
                </script>
                <?php
            });
        }
    }
}
add_action('wp', 'add_prefecture_rich_content');

/**
 * 4. H1タグとページコンテンツの強化（県名を自然に含む）
 */
function add_prefecture_content_enhancement() {
    $prefecture_slug = get_query_var('prefecture');
    
    if ($prefecture_slug) {
        $prefectures = get_prefecture_mapping();
        
        if (isset($prefectures[$prefecture_slug])) {
            $pref_name = $prefectures[$prefecture_slug]['name'];
            $classroom_count = get_prefecture_classroom_count($prefecture_slug);
            
            add_action('wp_head', function() use ($pref_name, $classroom_count) {
                ?>
                <script>
                jQuery(document).ready(function($) {
                    // H1タグを動的に変更
                    var $h1 = $('h1').first();
                    if ($h1.length) {
                        $h1.html('<?php echo esc_js($pref_name); ?>の放課後等デイサービスの事業所の一覧');
                    }
                });
                </script>
                <?php
            }, 99);
        }
    }
}
add_action('wp', 'add_prefecture_content_enhancement');

/**
 * 5. 都道府県コード取得関数（既存関数がある場合はスキップ）
 */
if (!function_exists('get_prefecture_code')) {
    function get_prefecture_code($pref_name) {
        $codes = array(
            '北海道' => '01', '青森県' => '02', '岩手県' => '03', '宮城県' => '04',
            '秋田県' => '05', '山形県' => '06', '福島県' => '07', '茨城県' => '08',
            '栃木県' => '09', '群馬県' => '10', '埼玉県' => '11', '千葉県' => '12',
            '東京都' => '13', '神奈川県' => '14', '新潟県' => '15', '富山県' => '16',
            '石川県' => '17', '福井県' => '18', '山梨県' => '19', '長野県' => '20',
            '岐阜県' => '21', '静岡県' => '22', '愛知県' => '23', '三重県' => '24',
            '滋賀県' => '25', '京都府' => '26', '大阪府' => '27', '兵庫県' => '28',
            '奈良県' => '29', '和歌山県' => '30', '鳥取県' => '31', '島根県' => '32',
            '岡山県' => '33', '広島県' => '34', '山口県' => '35', '徳島県' => '36',
            '香川県' => '37', '愛媛県' => '38', '高知県' => '39', '福岡県' => '40',
            '佐賀県' => '41', '長崎県' => '42', '熊本県' => '43', '大分県' => '44',
            '宮崎県' => '45', '鹿児島県' => '46', '沖縄県' => '47'
        );
        
        return isset($codes[$pref_name]) ? $codes[$pref_name] : '13';
    }
}

/**
 * 6. パンくずリストの強化（県名含む）
 */
function enhance_breadcrumb_with_prefecture() {
    $prefecture_slug = get_query_var('prefecture');
    
    if ($prefecture_slug) {
        $prefectures = get_prefecture_mapping();
        
        if (isset($prefectures[$prefecture_slug])) {
            $pref_name = $prefectures[$prefecture_slug]['name'];
            
            add_filter('wpseo_breadcrumb_links', function($links) use ($pref_name) {
                // Yoast SEOのパンくずリストをカスタマイズ
                if (is_array($links)) {
                    $links[] = array(
                        'url' => '',
                        'text' => $pref_name . 'の事業所の一覧'
                    );
                }
                return $links;
            });
        }
    }
}
add_action('wp', 'enhance_breadcrumb_with_prefecture');

// 元のコードから継続する必要な関数群（重複チェック付き）

/**
 * 実際のデータから教室数を取得する関数（既存関数がある場合はスキップ）
 */
if (!function_exists('get_prefecture_classroom_count')) {
    function get_prefecture_classroom_count($prefecture_slug) {
        // 元のコードと同じ
        return get_default_classroom_count($prefecture_slug);
    }
}

if (!function_exists('get_default_classroom_count')) {
    function get_default_classroom_count($prefecture_slug) {
        $counts = array(
            'hokkaido' => 6, 'aomori' => 2, 'iwate' => 2, 'fukushima' => 2,
            'ibaraki' => 9, 'tochigi' => 2, 'gunma' => 2, 'saitama' => 6,
            'chiba' => 30, 'tokyo' => 18, 'kanagawa' => 7, 'ishikawa' => 8,
            'nagano' => 20, 'aichi' => 2, 'osaka' => 1, 'hyogo' => 6,
            'hiroshima' => 2, 'ehime' => 3, 'fukuoka' => 4, 'saga' => 11,
            'oita' => 1, 'kumamoto' => 2, 'miyazaki' => 2, 'kagoshima' => 5,
            'okinawa' => 18,
        );
        
        return isset($counts[$prefecture_slug]) ? $counts[$prefecture_slug] : 0;
    }
}

if (!function_exists('get_prefecture_coordinates')) {
    function get_prefecture_coordinates($pref_name) {
        $coordinates = array(
            '北海道' => '43.0642,141.3469', '青森県' => '40.8244,140.7397',
            '岩手県' => '39.7036,141.1527', '宮城県' => '38.2682,140.8694',
            '秋田県' => '39.7186,140.1023', '山形県' => '38.2404,140.3633',
            '福島県' => '37.7503,140.4676', '茨城県' => '36.3417,140.4468',
            '栃木県' => '36.5657,139.8836', '群馬県' => '36.3911,139.0608',
            '埼玉県' => '35.8575,139.6489', '千葉県' => '35.6047,140.1233',
            '東京都' => '35.6762,139.6503', '神奈川県' => '35.4478,139.6425',
            '新潟県' => '37.9026,139.0235', '富山県' => '36.6959,137.2113',
            '石川県' => '36.5944,136.6256', '福井県' => '36.0652,136.2217',
            '山梨県' => '35.6642,138.5687', '長野県' => '36.6513,138.1810',
            '岐阜県' => '35.3912,136.7223', '静岡県' => '34.9769,138.3831',
            '愛知県' => '35.1802,136.9066', '三重県' => '34.7303,136.5086',
            '滋賀県' => '35.0045,135.8686', '京都府' => '35.0211,135.7539',
            '大阪府' => '34.6937,135.5023', '兵庫県' => '34.6913,135.1830',
            '奈良県' => '34.6851,135.8048', '和歌山県' => '34.2261,135.1675',
            '鳥取県' => '35.5038,134.2382', '島根県' => '35.4723,133.0505',
            '岡山県' => '34.6617,133.9341', '広島県' => '34.3963,132.4596',
            '山口県' => '34.1859,131.4706', '徳島県' => '34.0658,134.5593',
            '香川県' => '34.3401,134.0434', '愛媛県' => '33.8416,132.7658',
            '高知県' => '33.5597,133.5311', '福岡県' => '33.6064,130.4181',
            '佐賀県' => '33.2494,130.2989', '長崎県' => '32.7503,129.8681',
            '熊本県' => '32.7898,130.7417', '大分県' => '33.2382,131.6126',
            '宮崎県' => '31.9077,131.4202', '鹿児島県' => '31.5602,130.5581',
            '沖縄県' => '26.2124,127.6792'
        );
        
        return isset($coordinates[$pref_name]) ? $coordinates[$pref_name] : '35.6762,139.6503';
    }
}

if (!function_exists('get_prefecture_classrooms')) {
    function get_prefecture_classrooms($prefecture_slug) {
        $prefectures = get_prefecture_mapping();
        
        if (!isset($prefectures[$prefecture_slug])) {
            return array();
        }
        
        $pref_id = $prefectures[$prefecture_slug]['id'];
        
        // 実際のHTMLデータから教室情報を抽出
        $classroom_data = array(
            '1' => array( // 北海道
                array('name' => 'ピープルこどもプラス', 'address' => '北海道札幌市白石区栄通3-1-33', 'url' => 'http://people-kodomo.com/', 'city' => '札幌市'),
                array('name' => 'みなぽっけ', 'address' => '北海道小樽市桜3丁目4-7', 'url' => 'https://minapokke.shinnki.com/', 'city' => '小樽市'),
                array('name' => 'こどもプラス苫小牧教室', 'address' => '北海道苫小牧市双葉町1丁目19番14号', 'url' => 'http://kp-tomakomai.com/', 'city' => '苫小牧市'),
            ),
            '13' => array( // 東京都
                array('name' => 'こどもプラス八王子教室', 'address' => '東京都八王子市天神町24-3', 'url' => 'http://hachiouji-hattatsu.com/', 'city' => '八王子市'),
                array('name' => 'チャイルドブレイン東浅川教室', 'address' => '東京都八王子市東浅川町1-11', 'url' => 'http://jcbh.co.jp/', 'city' => '八王子市'),
            ),
            '12' => array( // 千葉県
                array('name' => 'こどもプラス柏教室', 'address' => '千葉県柏市千代田1-3-12', 'url' => 'http://kodomo-plus-kashiwa.com/', 'city' => '柏市'),
                array('name' => 'こどもプラス我孫子教室', 'address' => '千葉県我孫子市緑1-1-3', 'url' => 'http://kodomo-plus-abiko.com/', 'city' => '我孫子市'),
            ),
        );
        
        return isset($classroom_data[$pref_id]) ? $classroom_data[$pref_id] : array();
    }
}





/**
 * 検索結果でのタイトル表示最適化（SEOプラグインなし版）
 * 地域別求人ページで検索エンジンに表示されるタイトルを改善
 */

/**
 * 1. タイトルタグの最適化（地域名を前面に）
 */
function optimize_job_location_title($title_parts) {
    // 地域タクソノミーページの場合
    if (is_tax('job_location')) {
        $term = get_queried_object();
        $location_name = $term->name;
        $site_name = get_bloginfo('name');
        
        // 求人数を取得
        $job_count = get_job_count_for_term($term);
        
        // 地域名を前面に出したタイトル
        $new_title = "{$location_name}求人-放課後等デイサービス・児童発達支援";
        
        // 求人数がある場合は追加
        if ($job_count > 0) {
            $new_title = "{$location_name}求人{$job_count}件-放課後等デイサービス・児童発達支援";
        }
        
        // サイト名は最後に
        $title_parts['title'] = $new_title;
        $title_parts['site'] = $site_name;
        
        return $title_parts;
    }
    
    // 職種タクソノミーページの場合
    if (is_tax('job_position')) {
        $term = get_queried_object();
        $position_name = $term->name;
        $site_name = get_bloginfo('name');
        $job_count = get_job_count_for_term($term);
        
        $new_title = "{$position_name}求人";
        if ($job_count > 0) {
            $new_title .= "{$job_count}件";
        }
        $new_title .= "-放課後等デイサービス・児童発達支援";
        
        $title_parts['title'] = $new_title;
        $title_parts['site'] = $site_name;
        
        return $title_parts;
    }
    
    // 複合条件ページ（地域+職種など）の場合
    if (is_post_type_archive('job') && function_exists('get_job_filters_from_url') && !empty(get_job_filters_from_url())) {
        $filters = get_job_filters_from_url();
        $title_components = array();
        
        // 地域を最初に
        if (!empty($filters['location'])) {
            $location_term = get_term_by('slug', $filters['location'], 'job_location');
            if ($location_term) {
                $title_components[] = $location_term->name;
            }
        }
        
        // 職種を追加
        if (!empty($filters['position'])) {
            $position_term = get_term_by('slug', $filters['position'], 'job_position');
            if ($position_term) {
                $title_components[] = $position_term->name;
            }
        }
        
        // その他の条件を追加
        if (!empty($filters['type'])) {
            $type_term = get_term_by('slug', $filters['type'], 'job_type');
            if ($type_term) {
                $title_components[] = $type_term->name;
            }
        }
        
        if (!empty($title_components)) {
            $conditions = implode('×', $title_components);
            $new_title = "{$conditions}求人-放課後等デイサービス・児童発達支援";
            $title_parts['title'] = $new_title;
        }
        
        return $title_parts;
    }
    
    return $title_parts;
}
add_filter('document_title_parts', 'optimize_job_location_title', 10, 1);

/**
 * 2. メタディスクリプションも地域名を強調
 */
function optimize_job_location_meta_description() {
    // 地域タクソノミーページの場合
    if (is_tax('job_location')) {
        $term = get_queried_object();
        $location_name = $term->name;
        $job_count = get_job_count_for_term($term);
        
        $description = "{$location_name}の放課後等デイサービス・児童発達支援の求人を{$job_count}件掲載中。{$location_name}エリアで正社員・パート・アルバイトの募集情報をお探しなら当サイトへ。未経験歓迎、研修充実の職場多数。";
        
        add_action('wp_head', function() use ($description) {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }, 1);
    }
    
    // 職種タクソノミーページの場合
    if (is_tax('job_position')) {
        $term = get_queried_object();
        $position_name = $term->name;
        $job_count = get_job_count_for_term($term);
        
        $description = "放課後等デイサービス・児童発達支援での{$position_name}求人を{$job_count}件掲載。全国の{$position_name}募集情報を毎日更新。正社員・パート・未経験歓迎の求人多数。療育・運動あそび指導員として働きませんか。";
        
        add_action('wp_head', function() use ($description) {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }, 1);
    }
}
add_action('wp', 'optimize_job_location_meta_description');

/**
 * 3. Open Graphのタイトルも最適化
 */
function optimize_job_og_title() {
    if (is_tax('job_location')) {
        $term = get_queried_object();
        $location_name = $term->name;
        $job_count = get_job_count_for_term($term);
        
        $og_title = "{$location_name}求人{$job_count}件-放課後等デイサービス・児童発達支援";
        
        add_action('wp_head', function() use ($og_title) {
            echo '<meta property="og:title" content="' . esc_attr($og_title) . '">' . "\n";
        }, 5);
    }
    
    if (is_tax('job_position')) {
        $term = get_queried_object();
        $position_name = $term->name;
        $job_count = get_job_count_for_term($term);
        
        $og_title = "{$position_name}求人{$job_count}件-放課後等デイサービス・児童発達支援";
        
        add_action('wp_head', function() use ($og_title) {
            echo '<meta property="og:title" content="' . esc_attr($og_title) . '">' . "\n";
        }, 5);
    }
}
add_action('wp', 'optimize_job_og_title');

/**
 * 4. 構造化データのタイトルも最適化
 */
function optimize_job_structured_data_title() {
    if (is_tax('job_location') || is_tax('job_position') || 
        (is_post_type_archive('job') && !empty(get_job_filters_from_url()))) {
        
        add_action('wp_head', function() {
            $schema_data = generate_job_listing_schema();
            
            if ($schema_data) {
                echo '<script type="application/ld+json">';
                echo json_encode($schema_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                echo '</script>' . "\n";
            }
        }, 10);
    }
}
add_action('wp', 'optimize_job_structured_data_title');

/**
 * 5. 求人リスティング用の構造化データ生成
 */
function generate_job_listing_schema() {
    $current_url = home_url(add_query_arg(array(), ''));
    
    if (is_tax('job_location')) {
        $term = get_queried_object();
        $location_name = $term->name;
        $job_count = get_job_count_for_term($term);
        
        return array(
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => "{$location_name}求人{$job_count}件-放課後等デイサービス・児童発達支援",
            'description' => "{$location_name}の放課後等デイサービス・児童発達支援の求人情報",
            'url' => $current_url,
            'mainEntity' => array(
                '@type' => 'ItemList',
                'name' => "{$location_name}の求人一覧",
                'numberOfItems' => $job_count,
                'itemListOrder' => 'https://schema.org/ItemListOrderDescending'
            ),
            'breadcrumb' => array(
                '@type' => 'BreadcrumbList',
                'itemListElement' => array(
                    array(
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'ホーム',
                        'item' => home_url()
                    ),
                    array(
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => '求人情報',
                        'item' => home_url('/jobs/')
                    ),
                    array(
                        '@type' => 'ListItem',
                        'position' => 3,
                        'name' => "{$location_name}の求人",
                        'item' => $current_url
                    )
                )
            )
        );
    }
    
    return null;
}

/**
 * 6. タームの求人数を取得するヘルパー関数
 */
if (!function_exists('get_job_count_for_term')) {
    function get_job_count_for_term($term) {
        $args = array(
            'post_type' => 'job',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => $term->taxonomy,
                    'field' => 'term_id',
                    'terms' => $term->term_id
                )
            ),
            'fields' => 'ids'
        );
        
        $query = new WP_Query($args);
        $count = $query->found_posts;
        wp_reset_postdata();
        
        return $count;
    }
}

/**
 * 7. HTMLタイトルタグの直接出力（確実にタイトルを設定）
 */
function force_optimized_title_output() {
    if (is_tax('job_location')) {
        $term = get_queried_object();
        $location_name = $term->name;
        $job_count = get_job_count_for_term($term);
        $site_name = get_bloginfo('name');
        
        $title = "{$location_name}求人{$job_count}件-放課後等デイサービス・児童発達支援｜{$site_name}";
        
        // 他のタイトルタグを削除
        remove_action('wp_head', '_wp_render_title_tag', 1);
        
        // 新しいタイトルタグを出力
        add_action('wp_head', function() use ($title) {
            echo '<title>' . esc_html($title) . '</title>' . "\n";
        }, 1);
    }
    
    if (is_tax('job_position')) {
        $term = get_queried_object();
        $position_name = $term->name;
        $job_count = get_job_count_for_term($term);
        $site_name = get_bloginfo('name');
        
        $title = "{$position_name}求人{$job_count}件-放課後等デイサービス・児童発達支援｜{$site_name}";
        
        // 他のタイトルタグを削除
        remove_action('wp_head', '_wp_render_title_tag', 1);
        
        // 新しいタイトルタグを出力
        add_action('wp_head', function() use ($title) {
            echo '<title>' . esc_html($title) . '</title>' . "\n";
        }, 1);
    }
}
add_action('wp', 'force_optimized_title_output');

/**
 * 8. H1タグも地域名を前面に（検索エンジンがH1を参考にする場合もある）
 */
function optimize_h1_for_search_engines() {
    if (is_tax('job_location') || is_tax('job_position')) {
        add_action('wp_footer', function() {
            ?>
            <script>
            jQuery(document).ready(function($) {
                var $h1 = $('h1').first();
                if ($h1.length) {
                    <?php if (is_tax('job_location')): ?>
                        <?php $term = get_queried_object(); ?>
                        <?php $location_name = $term->name; ?>
                        <?php $job_count = get_job_count_for_term($term); ?>
                        $h1.text('<?php echo esc_js($location_name); ?>の放課後等デイサービス求人<?php echo $job_count; ?>件');
                    <?php endif; ?>
                    
                    <?php if (is_tax('job_position')): ?>
                        <?php $term = get_queried_object(); ?>
                        <?php $position_name = $term->name; ?>
                        <?php $job_count = get_job_count_for_term($term); ?>
                        $h1.text('<?php echo esc_js($position_name); ?>求人<?php echo $job_count; ?>件｜放課後等デイサービス');
                    <?php endif; ?>
                }
            });
            </script>
            <?php
        });
    }
}
add_action('wp', 'optimize_h1_for_search_engines');

/**
 * 9. サイトマップのタイトルも最適化
 */
function optimize_sitemap_entry_title($entry, $post) {
    if ($post->post_type === 'job') {
        // 求人詳細ページのタイトルも地域名を含めて最適化
        $job_locations = get_the_terms($post->ID, 'job_location');
        $facility_name = get_post_meta($post->ID, 'facility_name', true);
        
        if ($job_locations && !is_wp_error($job_locations)) {
            $location_name = $job_locations[0]->name;
            if ($facility_name) {
                $entry['title'] = "{$location_name}｜{$facility_name}｜放課後等デイサービス求人";
            }
        }
    }
    return $entry;
}
add_filter('wp_sitemaps_posts_entry', 'optimize_sitemap_entry_title', 10, 2);

/**
 * 9. canonical URLも正しく設定
 */
function set_optimized_canonical_url() {
    if (is_tax('job_location') || is_tax('job_position')) {
        remove_action('wp_head', 'rel_canonical');
        
        $current_url = home_url(add_query_arg(array(), ''));
        echo '<link rel="canonical" href="' . esc_url($current_url) . '" />' . "\n";
    }
}
add_action('wp_head', 'set_optimized_canonical_url', 1);

/**
 * 10. 検索エンジンボット向けの追加ヒント
 */
function add_search_engine_hints() {
    if (is_tax('job_location')) {
        $term = get_queried_object();
        $location_name = $term->name;
        
        add_action('wp_head', function() use ($location_name) {
            echo '<meta name="subject" content="' . esc_attr($location_name) . '求人情報">' . "\n";
            echo '<meta name="classification" content="求人,雇用,仕事">' . "\n";
            echo '<meta name="geo.placename" content="' . esc_attr($location_name) . '">' . "\n";
        }, 3);
    }
}
add_action('wp', 'add_search_engine_hints');

/**
 * 11. ページ読み込み時の動的タイトル更新（念のため）
 */
function dynamic_title_update() {
    if (is_tax('job_location') || is_tax('job_position')) {
        add_action('wp_head', function() {
            ?>
            <script>
            // ページ読み込み完了後にタイトルを確実に設定
            window.addEventListener('load', function() {
                <?php if (is_tax('job_location')): ?>
                    <?php $term = get_queried_object(); ?>
                    <?php $location_name = $term->name; ?>
                    <?php $job_count = get_job_count_for_term($term); ?>
                    document.title = '<?php echo esc_js($location_name); ?>求人<?php echo $job_count; ?>件-放課後等デイサービス・児童発達支援｜<?php echo esc_js(get_bloginfo('name')); ?>';
                <?php endif; ?>
                
                <?php if (is_tax('job_position')): ?>
                    <?php $term = get_queried_object(); ?>
                    <?php $position_name = $term->name; ?>
                    <?php $job_count = get_job_count_for_term($term); ?>
                    document.title = '<?php echo esc_js($position_name); ?>求人<?php echo $job_count; ?>件-放課後等デイサービス・児童発達支援｜<?php echo esc_js(get_bloginfo('name')); ?>';
                <?php endif; ?>
            });
            </script>
            <?php
        }, 99);
    }
}
add_action('wp', 'dynamic_title_update');




/**
 * 地域別教室ページのSEO最適化（完全版）
 * functions.phpに追加してください
 */

/**
 * 1. 教室ページ（classname taxonomy）のタイトルとメタタグ強化
 */
function optimize_classroom_seo() {
    // classnameタクソノミーページでのみ実行
    if (is_tax('classname')) {
        $term = get_queried_object();
        $site_name = get_bloginfo('name');
        
        // ACFフィールドから情報を取得
        $class_name = get_field('claas', 'classname_' . $term->term_id) ?: $term->name;
        $address = get_field('addressa', 'classname_' . $term->term_id);
        $phone = get_field('tella', 'classname_' . $term->term_id);
        $website = get_field('web-urla', 'classname_' . $term->term_id);
        
        // 住所から都道府県・市区町村を抽出
        $location_info = extract_location_from_address($address);
        $prefecture = $location_info['prefecture'];
        $city = $location_info['city'];
        
        // SEO最適化されたタイトル（地域名を前面に）
        if ($prefecture && $city) {
            $seo_title = "{$prefecture}{$city} -{$class_name} | 放課後等デイサービス・児童発達支援の求人　{$site_name}";
        } else {
            $seo_title = "{$class_name} | 放課後等デイサービス・児童発達支援の求人　{$site_name}";
        }
        
        // メタディスクリプション（地域名を3-4回含む）
        if ($prefecture && $city) {
            $description = "{$prefecture}{$city}の放課後等デイサービス・児童発達支援なら{$class_name}。{$prefecture}{$city}エリアで運動療育・発達障害・ADHDのお子さまを支援。{$prefecture}{$city}の教室で正社員・パート求人募集中。未経験歓迎・研修充実。";
        } else {
            $description = "{$class_name}は放課後等デイサービス・児童発達支援で運動療育を提供。発達障害・ADHDのお子さまを専門支援。正社員・パート求人募集中。";
        }
        
        add_action('wp_head', function() use ($seo_title, $description, $class_name, $prefecture, $city, $address, $phone, $website, $term) {
            // タイトルタグを削除して新しく出力
            remove_action('wp_head', '_wp_render_title_tag', 1);
            echo '<title>' . esc_html($seo_title) . '</title>' . "\n";
            
            // メタディスクリプション
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
            
            // 地域特化キーワード
            if ($prefecture && $city) {
                $keywords = "{$prefecture},{$city},放課後等デイサービス,{$prefecture} {$city} 児童発達支援,{$prefecture} {$city} 発達障害,{$prefecture} {$city} ADHD,{$class_name},{$prefecture} {$city} 療育,運動療育,{$prefecture} {$city} 求人";
            } else {
                $keywords = "放課後等デイサービス,児童発達支援,発達障害,ADHD,{$class_name},療育,運動療育,求人";
            }
            echo '<meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
            
            // 地域情報メタタグ
            if ($prefecture && $city) {
                $prefecture_code = get_prefecture_code_by_name($prefecture);
                echo '<meta name="geo.region" content="JP-' . $prefecture_code . '">' . "\n";
                echo '<meta name="geo.placename" content="' . esc_attr($city . ', ' . $prefecture) . '">' . "\n";
            }
            
            // canonical URL
            echo '<link rel="canonical" href="' . esc_url(get_term_link($term)) . '">' . "\n";
            
            // Open Graph
            echo '<meta property="og:title" content="' . esc_attr($seo_title) . '">' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
            echo '<meta property="og:type" content="website">' . "\n";
            echo '<meta property="og:url" content="' . esc_url(get_term_link($term)) . '">' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
            echo '<meta property="og:locale" content="ja_JP">' . "\n";
            
            // Twitter Card
            echo '<meta name="twitter:card" content="summary">' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr($seo_title) . '">' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr(mb_substr($description, 0, 200)) . '">' . "\n";
            
            // 構造化データ（LocalBusiness）
            generate_classroom_structured_data($class_name, $address, $phone, $website, $prefecture, $city, $term);
            
        }, 1);
    }
}
add_action('wp', 'optimize_classroom_seo');

/**
 * 2. 住所から都道府県・市区町村を抽出する関数
 */
function extract_location_from_address($address) {
    if (empty($address)) {
        return array('prefecture' => '', 'city' => '');
    }
    
    $prefecture = '';
    $city = '';
    
    // 都道府県を抽出
    $prefectures = array(
        '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
        '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
        '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
        '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
        '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
        '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
        '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'
    );
    
    foreach ($prefectures as $pref) {
        if (mb_strpos($address, $pref) !== false) {
            $prefecture = $pref;
            break;
        }
    }
    
    // 市区町村を抽出
    if (preg_match('/' . preg_quote($prefecture, '/') . '(.+?)(?:[町|村|丁目|番地])/u', $address, $matches)) {
        $city_part = $matches[1];
        
        // 市区を抽出
        if (preg_match('/(.+?)[市|区|町|村]/u', $city_part, $city_matches)) {
            $city = $city_matches[1] . $city_matches[0][mb_strlen($city_matches[1])];
        }
    }
    
    return array(
        'prefecture' => $prefecture,
        'city' => $city
    );
}

/**
 * 3. 教室の構造化データ生成
 */
function generate_classroom_structured_data($class_name, $address, $phone, $website, $prefecture, $city, $term) {
    $structured_data = array(
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => $class_name,
        'description' => ($prefecture && $city) ? 
            "{$prefecture}{$city}で運動療育を中心とした放課後等デイサービス・児童発達支援を提供" :
            "運動療育を中心とした放課後等デイサービス・児童発達支援を提供",
        'url' => get_term_link($term),
        'address' => array(
            '@type' => 'PostalAddress',
            'streetAddress' => $address,
            'addressLocality' => $city,
            'addressRegion' => $prefecture,
            'addressCountry' => 'JP'
        ),
        'openingHours' => array('Mo-Fr 10:00-18:00'),
        'serviceArea' => array(
            '@type' => 'GeoCircle',
            'geoMidpoint' => array(
                '@type' => 'GeoCoordinates'
            ),
            'geoRadius' => '5000'
        ),
        'hasOfferCatalog' => array(
            '@type' => 'OfferCatalog',
            'name' => '放課後等デイサービス・児童発達支援',
            'itemListElement' => array(
                array(
                    '@type' => 'Offer',
                    'itemOffered' => array(
                        '@type' => 'Service',
                        'name' => '放課後等デイサービス',
                        'description' => '発達障害・ADHDのお子さま向け運動療育'
                    )
                ),
                array(
                    '@type' => 'Offer',
                    'itemOffered' => array(
                        '@type' => 'Service',
                        'name' => '児童発達支援',
                        'description' => '未就学児向け発達支援・運動療育'
                    )
                )
            )
        ),
        'makesOffer' => array(
            '@type' => 'Offer',
            'itemOffered' => array(
                '@type' => 'JobPosting',
                'title' => "{$class_name}の求人募集",
                'description' => ($prefecture && $city) ?
                    "{$prefecture}{$city}で放課後等デイサービス・児童発達支援スタッフを募集" :
                    "放課後等デイサービス・児童発達支援スタッフを募集"
            )
        )
    );
    
    if ($phone) {
        $structured_data['telephone'] = $phone;
    }
    
    if ($website) {
        $structured_data['sameAs'] = array($website);
    }
    
    // パンくずリストも追加
    $breadcrumb = array(
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => array(
            array(
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'ホーム',
                'item' => home_url()
            ),
            array(
                '@type' => 'ListItem',
                'position' => 2,
                'name' => '求人情報',
                'item' => home_url('/jobs/')
            )
        )
    );
    
    if ($prefecture && $city) {
        $breadcrumb['itemListElement'][] = array(
            '@type' => 'ListItem',
            'position' => 3,
            'name' => "{$prefecture}{$city}の教室",
            'item' => get_term_link($term)
        );
    } else {
        $breadcrumb['itemListElement'][] = array(
            '@type' => 'ListItem',
            'position' => 3,
            'name' => $class_name,
            'item' => get_term_link($term)
        );
    }
    
    echo '<script type="application/ld+json">';
    echo json_encode($structured_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    echo '</script>' . "\n";
    
    echo '<script type="application/ld+json">';
    echo json_encode($breadcrumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo '</script>' . "\n";
}

/**
 * 4. H1タグとページコンテンツの動的変更
 */
function optimize_classroom_content() {
    if (is_tax('classname')) {
        add_action('wp_footer', function() {
            ?>
            <script>
            jQuery(document).ready(function($) {
                // H1タグを地域名を含めて最適化
                var $h1 = $('.recruitment-main-title');
                if ($h1.length) {
                    var currentText = $h1.text();
                    
                    // ACFフィールドから情報を取得（PHPから渡す）
                    var classroomData = {
                        className: <?php echo json_encode(get_field('claas', 'classname_' . get_queried_object()->term_id)); ?>,
                        address: <?php echo json_encode(get_field('addressa', 'classname_' . get_queried_object()->term_id)); ?>
                    };
                    
                    if (classroomData.address) {
                        // 住所から都道府県・市を抽出（簡易版）
                        var locationMatch = classroomData.address.match(/(.*?[都道府県])(.*?[市区町村])/);
                        if (locationMatch) {
                            var prefecture = locationMatch[1];
                            var city = locationMatch[2];
                            $h1.html(prefecture + city + ' -' + classroomData.className + 'の<br>求人情報一覧');
                        }
                    }
                }
                
                // メタディスクリプションを動的に追加（念のため）
                if (!$('meta[name="description"]').length) {
                    var description = '<?php 
                        $term = get_queried_object();
                        $class_name = get_field("claas", "classname_" . $term->term_id);
                        $address = get_field("addressa", "classname_" . $term->term_id);
                        $location_info = extract_location_from_address($address);
                        if ($location_info["prefecture"] && $location_info["city"]) {
                            echo esc_js($location_info["prefecture"] . $location_info["city"] . "の放課後等デイサービス・児童発達支援なら" . $class_name . "。" . $location_info["prefecture"] . $location_info["city"] . "エリアで運動療育・発達障害・ADHDのお子さまを支援。");
                        }
                    ?>';
                    
                    if (description) {
                        $('head').append('<meta name="description" content="' + description + '">');
                    }
                }
            });
            </script>
            <?php
        });
    }
}
add_action('wp', 'optimize_classroom_content');

/**
 * 5. 教室ページ用のサイトマップ強化
 */
function enhance_classroom_sitemap() {
    add_filter('wp_sitemaps_taxonomies_entry', function($entry, $term, $taxonomy) {
        if ($taxonomy === 'classname') {
            $class_name = get_field('claas', 'classname_' . $term->term_id) ?: $term->name;
            $address = get_field('addressa', 'classname_' . $term->term_id);
            $location_info = extract_location_from_address($address);
            
            if ($location_info['prefecture'] && $location_info['city']) {
                $entry['loc'] = get_term_link($term);
                $entry['lastmod'] = date('Y-m-d\TH:i:s+00:00');
                $entry['changefreq'] = 'monthly';
                $entry['priority'] = 0.9; // 教室ページは高優先度
                $entry['title'] = $location_info['prefecture'] . $location_info['city'] . ' -' . $class_name;
            }
        }
        return $entry;
    }, 10, 3);
}
add_action('init', 'enhance_classroom_sitemap');

/**
 * 6. robots.txtで教室ページを明示的に許可
 */
function allow_classroom_pages_in_robots($output, $public) {
    if ($public) {
        $output .= "\n# Classroom pages - Allow all\n";
        $output .= "Allow: /classname/\n";
        $output .= "Allow: /classname/*\n";
    }
    return $output;
}
add_filter('robots_txt', 'allow_classroom_pages_in_robots', 10, 2);

/**
 * 7. 地域検索での内部リンク強化
 */
function add_classroom_internal_links() {
    if (is_tax('classname')) {
        $term = get_queried_object();
        $address = get_field('addressa', 'classname_' . $term->term_id);
        $location_info = extract_location_from_address($address);
        
        if ($location_info['prefecture']) {
            add_action('wp_head', function() use ($location_info, $term) {
                // 同じ都道府県の他の教室へのリンク
                $related_terms = get_terms(array(
                    'taxonomy' => 'classname',
                    'hide_empty' => false,
                    'exclude' => array($term->term_id),
                    'number' => 10
                ));
                
                foreach ($related_terms as $related_term) {
                    $related_address = get_field('addressa', 'classname_' . $related_term->term_id);
                    if ($related_address && mb_strpos($related_address, $location_info['prefecture']) !== false) {
                        echo '<link rel="related" href="' . get_term_link($related_term) . '">' . "\n";
                    }
                }
            }, 20);
        }
    }
}
add_action('wp', 'add_classroom_internal_links');

/**
 * 8. 都道府県コードを名前から取得する関数
 */
function get_prefecture_code_by_name($prefecture_name) {
    $prefecture_codes = array(
        '北海道' => '01', '青森県' => '02', '岩手県' => '03', '宮城県' => '04',
        '秋田県' => '05', '山形県' => '06', '福島県' => '07', '茨城県' => '08',
        '栃木県' => '09', '群馬県' => '10', '埼玉県' => '11', '千葉県' => '12',
        '東京都' => '13', '神奈川県' => '14', '新潟県' => '15', '富山県' => '16',
        '石川県' => '17', '福井県' => '18', '山梨県' => '19', '長野県' => '20',
        '岐阜県' => '21', '静岡県' => '22', '愛知県' => '23', '三重県' => '24',
        '滋賀県' => '25', '京都府' => '26', '大阪府' => '27', '兵庫県' => '28',
        '奈良県' => '29', '和歌山県' => '30', '鳥取県' => '31', '島根県' => '32',
        '岡山県' => '33', '広島県' => '34', '山口県' => '35', '徳島県' => '36',
        '香川県' => '37', '愛媛県' => '38', '高知県' => '39', '福岡県' => '40',
        '佐賀県' => '41', '長崎県' => '42', '熊本県' => '43', '大分県' => '44',
        '宮崎県' => '45', '鹿児島県' => '46', '沖縄県' => '47'
    );
    
    return isset($prefecture_codes[$prefecture_name]) ? $prefecture_codes[$prefecture_name] : '13';
}

/**
 * 9. 教室ページ専用のスピードとパフォーマンス最適化
 */
function optimize_classroom_performance() {
    if (is_tax('classname')) {
        // 不要なスクリプトを削除
        add_action('wp_enqueue_scripts', function() {
            wp_dequeue_script('wp-embed');
        }, 100);
        
        // 重要なCSSを先読み
        add_action('wp_head', function() {
            echo '<style>';
            echo '.recruitment-main-title{font-size:28px;font-weight:bold;color:#333;margin-bottom:50px;text-align:center;}';
            echo '.recruitment-info-section{margin-bottom:60px;}';
            echo '</style>';
        }, 1);
    }
}
add_action('wp', 'optimize_classroom_performance');

/**
 * 10. 管理画面での教室SEO情報表示
 */
function add_classroom_seo_column($columns) {
    $columns['classroom_seo'] = 'SEO情報';
    return $columns;
}
add_filter('manage_edit-classname_columns', 'add_classroom_seo_column');

function show_classroom_seo_column($content, $column_name, $term_id) {
    if ($column_name === 'classroom_seo') {
        $address = get_field('addressa', 'classname_' . $term_id);
        $location_info = extract_location_from_address($address);
        
        if ($location_info['prefecture'] && $location_info['city']) {
            $seo_score = '<span style="color: green;">✓ 地域最適化済み</span><br>';
            $seo_score .= '<small>' . $location_info['prefecture'] . $location_info['city'] . '</small>';
        } else {
            $seo_score = '<span style="color: orange;">△ 住所要確認</span>';
        }
        
        return $seo_score;
    }
    return $content;
}
add_filter('manage_classname_custom_column', 'show_classroom_seo_column', 10, 3);

/**
 * 11. 自動でGoogle Search Consoleに更新を通知
 */
function ping_google_on_classroom_update($term_id, $tt_id, $taxonomy) {
    if ($taxonomy === 'classname') {
        // 遅延実行でGoogleに通知
        wp_schedule_single_event(time() + 300, 'ping_google_classroom_update', array($term_id));
    }
}
add_action('edited_classname', 'ping_google_on_classroom_update', 10, 3);

function ping_google_classroom_update($term_id) {
    $term = get_term($term_id, 'classname');
    if ($term && !is_wp_error($term)) {
        $sitemap_url = home_url('/wp-sitemap-taxonomies-classname-1.xml');
        $ping_url = 'https://www.google.com/ping?sitemap=' . urlencode($sitemap_url);
        wp_remote_get($ping_url, array('timeout' => 30));
    }
}
add_action('ping_google_classroom_update', 'ping_google_classroom_update');

/**
 * 12. 教室ページのAMP対応（オプション）
 */
function add_classroom_amp_support() {
    if (function_exists('amp_is_request') && amp_is_request() && is_tax('classname')) {
        $term = get_queried_object();
        $class_name = get_field('claas', 'classname_' . $term->term_id);
        $address = get_field('addressa', 'classname_' . $term->term_id);
        $location_info = extract_location_from_address($address);
        
        add_filter('amp_post_template_data', function($data) use ($class_name, $location_info) {
            if ($location_info['prefecture'] && $location_info['city']) {
                $data['post_title'] = $location_info['prefecture'] . $location_info['city'] . ' -' . $class_name;
            }
            return $data;
        });
    }
}
add_action('wp', 'add_classroom_amp_support');

/**
 * 13. WP-CLI対応（コマンドライン実行）
 */
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('classroom-seo', function($args, $assoc_args) {
        $action = $args[0] ?? 'check';
        
        switch ($action) {
            case 'check':
                $terms = get_terms(array(
                    'taxonomy' => 'classname',
                    'hide_empty' => false
                ));
                
                $optimized = 0;
                $needs_work = 0;
                
                foreach ($terms as $term) {
                    $address = get_field('addressa', 'classname_' . $term->term_id);
                    $location_info = extract_location_from_address($address);
                    
                    if ($location_info['prefecture'] && $location_info['city']) {
                        $optimized++;
                        WP_CLI::line("✓ {$term->name} - {$location_info['prefecture']}{$location_info['city']}");
                    } else {
                        $needs_work++;
                        WP_CLI::line("△ {$term->name} - 住所要確認");
                    }
                }
                
                WP_CLI::success("最適化済み: {$optimized}校, 要改善: {$needs_work}校");
                break;
                
            case 'generate-sitemap':
                wp_schedule_single_event(time() + 10, 'ping_google_sitemap_update');
                WP_CLI::success('サイトマップ更新をスケジュールしました');
                break;
                
            default:
                WP_CLI::error('利用可能なアクション: check, generate-sitemap');
        }
    });
}

/**
 * 14. デバッグ用：SEO情報確認
 */
function debug_classroom_seo() {
    if (is_tax('classname') && current_user_can('administrator') && isset($_GET['debug_classroom_seo'])) {
        $term = get_queried_object();
        $class_name = get_field('claas', 'classname_' . $term->term_id);
        $address = get_field('addressa', 'classname_' . $term->term_id);
        $location_info = extract_location_from_address($address);
        
        echo '<div style="background: white; padding: 20px; margin: 20px; border: 1px solid #ccc;">';
        echo '<h3>教室SEOデバッグ情報</h3>';
        echo '<p><strong>教室名:</strong> ' . esc_html($class_name) . '</p>';
        echo '<p><strong>住所:</strong> ' . esc_html($address) . '</p>';
        echo '<p><strong>都道府県:</strong> ' . esc_html($location_info['prefecture']) . '</p>';
        echo '<p><strong>市区町村:</strong> ' . esc_html($location_info['city']) . '</p>';
        echo '<p><strong>URL:</strong> ' . esc_url(get_term_link($term)) . '</p>';
        echo '</div>';
    }
}
add_action('wp_footer', 'debug_classroom_seo');



/**
 * 16. 地域別クエリ変数を追加
 */
function add_classroom_query_vars($vars) {
    $vars[] = 'classroom_area';
    $vars[] = 'classroom_city';
    return $vars;
}
add_filter('query_vars', 'add_classroom_query_vars');

/**
 * 17. 地域別教室検索機能
 */
function handle_regional_classroom_search() {
    $area = get_query_var('classroom_area');
    $city = get_query_var('classroom_city');
    
    if ($area) {
        $prefecture_mapping = array(
            'tokyo' => '東京都',
            'kanagawa' => '神奈川県',
            'chiba' => '千葉県',
            'saitama' => '埼玉県',
            'osaka' => '大阪府',
            'kyoto' => '京都府',
            'hyogo' => '兵庫県',
            'aichi' => '愛知県',
            'fukuoka' => '福岡県',
            'hokkaido' => '北海道'
        );
        
        $prefecture_name = isset($prefecture_mapping[$area]) ? $prefecture_mapping[$area] : '';
        
        if ($prefecture_name) {
            // 地域別ページのSEO設定
            add_action('wp_head', function() use ($prefecture_name, $city, $area) {
                $site_name = get_bloginfo('name');
                
                if ($city) {
                    $city_mapping = array(
                        'shibuya' => '渋谷区',
                        'shinjuku' => '新宿区',
                        'minato' => '港区',
                        'chiyoda' => '千代田区'
                    );
                    $city_name = isset($city_mapping[$city]) ? $city_mapping[$city] : $city;
                    
                    $title = "{$prefecture_name}{$city_name}の放課後等デイサービス・児童発達支援教室一覧｜{$site_name}";
                    $description = "{$prefecture_name}{$city_name}の放課後等デイサービス・児童発達支援なら{$site_name}。{$prefecture_name}{$city_name}エリアで運動療育・発達障害・ADHDのお子さまを支援する教室をご紹介。";
                } else {
                    $title = "{$prefecture_name}の放課後等デイサービス・児童発達支援教室一覧｜{$site_name}";
                    $description = "{$prefecture_name}の放課後等デイサービス・児童発達支援なら{$site_name}。{$prefecture_name}で運動療育・発達障害・ADHDのお子さまを支援する教室をご紹介。";
                }
                
                remove_action('wp_head', '_wp_render_title_tag', 1);
                echo '<title>' . esc_html($title) . '</title>' . "\n";
                echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
                
                // 構造化データ
                $structured_data = array(
                    '@context' => 'https://schema.org',
                    '@type' => 'WebPage',
                    'name' => $title,
                    'description' => $description,
                    'url' => home_url('/area/' . $area . ($city ? '/' . $city : '') . '/'),
                    'mainEntity' => array(
                        '@type' => 'ItemList',
                        'name' => ($city ? "{$prefecture_name}{$city_name}" : $prefecture_name) . 'の教室一覧',
                        'description' => ($city ? "{$prefecture_name}{$city_name}" : $prefecture_name) . 'の放課後等デイサービス・児童発達支援教室'
                    )
                );
                
                echo '<script type="application/ld+json">';
                echo json_encode($structured_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                echo '</script>' . "\n";
            }, 1);
        }
    }
}
add_action('wp', 'handle_regional_classroom_search');

/**
 * 18. 教室検索結果の絞り込み
 */
function filter_classrooms_by_region($query) {
    if (!is_admin() && $query->is_main_query()) {
        $area = get_query_var('classroom_area');
        $city = get_query_var('classroom_city');
        
        if ($area) {
            // 都道府県マッピング
            $prefecture_mapping = array(
                'tokyo' => '東京都',
                'kanagawa' => '神奈川県',
                'chiba' => '千葉県',
                'saitama' => '埼玉県',
                'osaka' => '大阪府',
                'kyoto' => '京都府',
                'hyogo' => '兵庫県',
                'aichi' => '愛知県',
                'fukuoka' => '福岡県',
                'hokkaido' => '北海道'
            );
            
            $prefecture_name = isset($prefecture_mapping[$area]) ? $prefecture_mapping[$area] : '';
            
            if ($prefecture_name) {
                $query->set('post_type', 'page');
                $query->set('meta_query', array(
                    array(
                        'key' => 'prefecture',
                        'value' => $prefecture_name,
                        'compare' => 'LIKE'
                    )
                ));
            }
        }
    }
}
add_action('pre_get_posts', 'filter_classrooms_by_region');

/**
 * 19. 地域別サイトマップ生成
 */
function generate_regional_classroom_sitemap() {
    $prefectures = array(
        'tokyo' => '東京都',
        'kanagawa' => '神奈川県',
        'chiba' => '千葉県',
        'saitama' => '埼玉県',
        'osaka' => '大阪府',
        'kyoto' => '京都府',
        'hyogo' => '兵庫県',
        'aichi' => '愛知県',
        'fukuoka' => '福岡県',
        'hokkaido' => '北海道'
    );
    
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    foreach ($prefectures as $slug => $name) {
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . esc_url(home_url('/area/' . $slug . '/')) . '</loc>' . "\n";
        $xml .= '    <lastmod>' . date('Y-m-d\TH:i:s+00:00') . '</lastmod>' . "\n";
        $xml .= '    <changefreq>monthly</changefreq>' . "\n";
        $xml .= '    <priority>0.8</priority>' . "\n";
        $xml .= '  </url>' . "\n";
    }
    
    $xml .= '</urlset>';
    
    return $xml;
}

/**
 * 20. 地域別サイトマップの出力エンドポイント
 */
function handle_regional_sitemap_request() {
    if (isset($_SERVER['REQUEST_URI']) && 
        strpos($_SERVER['REQUEST_URI'], '/regional-classroom-sitemap.xml') !== false) {
        
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex');
        
        echo generate_regional_classroom_sitemap();
        exit;
    }
}
add_action('init', 'handle_regional_sitemap_request', 1);

/**
 * 21. 検索エンジン向けhreflang属性追加（多地域対応）
 */
function add_classroom_hreflang() {
    if (is_tax('classname')) {
        $term = get_queried_object();
        $address = get_field('addressa', 'classname_' . $term->term_id);
        $location_info = extract_location_from_address($address);
        
        if ($location_info['prefecture']) {
            add_action('wp_head', function() use ($term, $location_info) {
                // 地域別のhreflang
                echo '<link rel="alternate" hreflang="ja-JP" href="' . esc_url(get_term_link($term)) . '">' . "\n";
                
                // 関連地域のページへのalternate
                $related_regions = array(
                    '東京都' => '/area/tokyo/',
                    '神奈川県' => '/area/kanagawa/',
                    '千葉県' => '/area/chiba/',
                    '埼玉県' => '/area/saitama/'
                );
                
                if (isset($related_regions[$location_info['prefecture']])) {
                    echo '<link rel="alternate" href="' . esc_url(home_url($related_regions[$location_info['prefecture']])) . '">' . "\n";
                }
            }, 5);
        }
    }
}
add_action('wp', 'add_classroom_hreflang');

/**
 * 22. 教室ページの表示速度最適化（画像遅延読み込み）
 */
function optimize_classroom_images() {
    if (is_tax('classname')) {
        add_action('wp_footer', function() {
            ?>
            <script>
            // 画像の遅延読み込み
            document.addEventListener('DOMContentLoaded', function() {
                var images = document.querySelectorAll('img[data-src]');
                
                var imageObserver = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                });
                
                images.forEach(function(img) {
                    imageObserver.observe(img);
                });
            });
            </script>
            <?php
        });
    }
}
add_action('wp', 'optimize_classroom_images');

/**
 * 23. 教室ページのCore Web Vitals最適化
 */
function optimize_classroom_core_web_vitals() {
    if (is_tax('classname')) {
        // クリティカルCSSをインライン化
        add_action('wp_head', function() {
            ?>
            <style>
            /* Critical CSS for above-the-fold content */
            .recruitment-header-banner{background:linear-gradient(135deg,#ffd966,#f6b73c);height:80px;width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw)}
            .recruitment-container{max-width:1000px;margin:0 auto;padding:40px 20px;background-color:#fff;min-height:calc(100vh - 80px)}
            .recruitment-main-title{text-align:center;font-size:28px;font-weight:bold;color:#333;margin-bottom:50px;letter-spacing:1px}
            .recruitment-intro-text{text-align:center;margin-bottom:60px;line-height:1.8}
            </style>
            <?php
        }, 1);
        
        // 非クリティカルCSSを遅延読み込み
        add_action('wp_footer', function() {
            ?>
            <script>
            // 非クリティカルCSSの遅延読み込み
            function loadCSS(href) {
                var link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = href;
                document.head.appendChild(link);
            }
            
            // ページ読み込み完了後に非クリティカルCSSを読み込み
            window.addEventListener('load', function() {
                // Contact Form 7のCSSなど
                loadCSS('<?php echo home_url(); ?>/wp-content/plugins/contact-form-7/includes/css/styles.css');
            });
            </script>
            <?php
        });
    }
}
add_action('wp', 'optimize_classroom_core_web_vitals');

/**
 * 24. モバイルファーストインデックス対応
 */
function optimize_classroom_mobile() {
    if (is_tax('classname')) {
        add_action('wp_head', function() {
            // モバイル最適化メタタグ
            echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">' . "\n";
            echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
            echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
            echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
            
            // PWA対応
            echo '<link rel="manifest" href="' . home_url('/manifest.json') . '">' . "\n";
            echo '<meta name="theme-color" content="#f6b73c">' . "\n";
        }, 2);
    }
}
add_action('wp', 'optimize_classroom_mobile');

/**
 * 25. 初期化とリライトルール更新
 */
function initialize_classroom_seo() {
    $initialized = get_option('classroom_seo_initialized');
    
    if (!$initialized) {
        // リライトルールをフラッシュ
        flush_rewrite_rules();
        
        // サイトマップ更新
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // 初回のGoogle通知
        wp_schedule_single_event(time() + 60, 'ping_google_sitemap_update');
        
        update_option('classroom_seo_initialized', true);
    }
}
add_action('init', 'initialize_classroom_seo', 999);




/**
 * 教室SEOデバッグ・修正版
 * functions.phpに追加してください
 */



/**
 * 2. より確実な地域抽出関数（改良版）
 */
function extract_location_from_address_improved($address) {
    if (empty($address)) {
        return array('prefecture' => '', 'city' => '');
    }
    
    $prefecture = '';
    $city = '';
    
    // 都道府県を抽出（より確実な方法）
    $prefectures = array(
        '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
        '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
        '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
        '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
        '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
        '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
        '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'
    );
    
    // 都道府県を見つける
    foreach ($prefectures as $pref) {
        if (mb_strpos($address, $pref) !== false) {
            $prefecture = $pref;
            break;
        }
    }
    
    // 市区町村を抽出（大幅改良版）
    if ($prefecture) {
        // 都道府県の後から市区町村を抽出
        $after_prefecture = mb_substr($address, mb_strpos($address, $prefecture) + mb_strlen($prefecture));
        
        // 手動マッピング（特殊な地名対応）
        $manual_city_mapping = array(
            'つくばみらい市' => 'つくばみらい市',
            'ひたちなか市' => 'ひたちなか市', 
            'かすみがうら市' => 'かすみがうら市',
            'つくば市' => 'つくば市',
            'いわき市' => 'いわき市',
            'ひたちなか市' => 'ひたちなか市'
        );
        
        foreach ($manual_city_mapping as $search_city => $display_city) {
            if (mb_strpos($after_prefecture, $search_city) !== false) {
                $city = $display_city;
                break;
            }
        }
        
        // まだ見つからない場合は通常のパターンマッチング
        $city_patterns = array(
            // ひらがなを含む複合市名に対応（つくばみらい市など）
            '/^([あ-んア-ン\p{Han}]+?市)/',   // ○○市（ひらがな・カタカナ・漢字対応）
            '/^([あ-んア-ン\p{Han}]+?区)/',   // ○○区
            '/^([あ-んア-ン\p{Han}]+?町)/',   // ○○町
            '/^([あ-んア-ン\p{Han}]+?村)/',   // ○○村
            // 数字が含まれる前までの文字を取得（番地などの前まで）
            '/^([^0-9]+?[市区町村])/',
            // より広範囲にマッチ
            '/^(.+?[市区町村])/'
        );
        
        foreach ($city_patterns as $pattern) {
            if (preg_match($pattern . 'u', $after_prefecture, $matches)) {
                $city = $matches[1];
                // 不適切な文字が含まれていないかチェック
                if (!preg_match('/[0-9\-丁目番地号]/', $city)) {
                    break;
                }
            }
        }
        
        // 特別区の場合（東京都の場合の特別処理）
        if ($prefecture === '東京都' && empty($city)) {
            if (preg_match('/([^0-9]+?区)/u', $after_prefecture, $matches)) {
                $city = $matches[1];
            }
        }
        
        // デバッグログ出力（一時的）
        if (current_user_can('administrator')) {
            error_log("Address debug: {$address}");
            error_log("Prefecture: {$prefecture}");
            error_log("After prefecture: {$after_prefecture}");
            error_log("Extracted city: {$city}");
        }
    }
    
    return array(
        'prefecture' => $prefecture,
        'city' => $city
    );
}

/**
 * 3. 強制的にタイトル・メタタグを設定（確実版）
 */
function force_classroom_seo_meta() {
    if (is_tax('classname')) {
        $term = get_queried_object();
        $site_name = get_bloginfo('name');
        
        // ACFフィールドから情報を取得
        $class_name = get_field('claas', 'classname_' . $term->term_id);
        if (!$class_name) {
            $class_name = $term->name;
        }
        
        $address = get_field('addressa', 'classname_' . $term->term_id);
        $phone = get_field('tella', 'classname_' . $term->term_id);
        $website = get_field('web-urla', 'classname_' . $term->term_id);
        
        // 住所から地域情報を抽出
        $location_info = extract_location_from_address_improved($address);
        $prefecture = $location_info['prefecture'];
        $city = $location_info['city'];
        
        // タイトルとディスクリプションを生成
        if ($prefecture && $city) {
            $seo_title = $prefecture . $city . ' -' . $class_name . ' | 放課後等デイサービス・児童発達支援の求人　' . $site_name;
            $description = $prefecture . $city . 'の放課後等デイサービス・児童発達支援なら' . $class_name . '。' . $prefecture . $city . 'エリアで運動療育・発達障害・ADHDのお子さまを支援。' . $prefecture . $city . 'の教室で正社員・パート求人募集中。未経験歓迎・研修充実。';
            
            // キーワード
            $keywords = $prefecture . ',' . $city . ',放課後等デイサービス,' . $prefecture . ' ' . $city . ' 児童発達支援,' . $prefecture . ' ' . $city . ' 発達障害,' . $class_name . ',療育,運動療育,求人';
        } else {
            // 地域情報がない場合のフォールバック
            $seo_title = $class_name . ' | 放課後等デイサービス・児童発達支援の求人　' . $site_name;
            $description = $class_name . 'は放課後等デイサービス・児童発達支援で運動療育を提供。発達障害・ADHDのお子さまを専門支援。正社員・パート求人募集中。未経験歓迎・研修充実。';
            $keywords = '放課後等デイサービス,児童発達支援,発達障害,ADHD,' . $class_name . ',療育,運動療育,求人';
        }
        
        // 最優先でメタタグを出力
        add_action('wp_head', function() use ($seo_title, $description, $keywords, $term) {
            // 既存のタイトルタグを削除
            remove_action('wp_head', '_wp_render_title_tag', 1);
            
            // Yoast SEOやRankMathのタイトルタグも削除
            add_filter('wpseo_title', function() use ($seo_title) { return $seo_title; });
            add_filter('rank_math/frontend/title', function() use ($seo_title) { return $seo_title; });
            
            // 新しいメタタグを出力
            echo '<title>' . esc_html($seo_title) . '</title>' . "\n";
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
            echo '<meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
            
            // canonical URL
            echo '<link rel="canonical" href="' . esc_url(get_term_link($term)) . '">' . "\n";
            
            // Open Graph
            echo '<meta property="og:title" content="' . esc_attr($seo_title) . '">' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
            echo '<meta property="og:type" content="website">' . "\n";
            echo '<meta property="og:url" content="' . esc_url(get_term_link($term)) . '">' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
            
        }, -1); // 最優先で実行
    }
}
add_action('wp', 'force_classroom_seo_meta', 1);

/**
 * 4. H1タグも確実に変更
 */
function force_classroom_h1_change() {
    if (is_tax('classname')) {
        $term = get_queried_object();
        $class_name = get_field('claas', 'classname_' . $term->term_id);
        if (!$class_name) {
            $class_name = $term->name;
        }
        
        $address = get_field('addressa', 'classname_' . $term->term_id);
        $location_info = extract_location_from_address_improved($address);
        
        add_action('wp_footer', function() use ($location_info, $class_name) {
            ?>
            <script>
            jQuery(document).ready(function($) {
                setTimeout(function() {
                    var $h1 = $('.recruitment-main-title, h1').first();
                    if ($h1.length) {
                        var prefecture = <?php echo json_encode($location_info['prefecture']); ?>;
                        var city = <?php echo json_encode($location_info['city']); ?>;
                        var className = <?php echo json_encode($class_name); ?>;
                        
                        if (prefecture && city) {
                            $h1.html(prefecture + city + ' -' + className + 'の<br>求人情報一覧');
                        } else {
                            $h1.html(className + 'の<br>求人情報一覧');
                        }
                        
                        console.log('H1タグを変更しました: ' + prefecture + city + ' -' + className);
                    }
                }, 500);
            });
            </script>
            <?php
        });
    }
}
add_action('wp', 'force_classroom_h1_change');

/**
 * 5. document.titleも動的に変更（JavaScript）
 */
function force_classroom_title_js() {
    if (is_tax('classname')) {
        $term = get_queried_object();
        $class_name = get_field('claas', 'classname_' . $term->term_id);
        if (!$class_name) {
            $class_name = $term->name;
        }
        
        $address = get_field('addressa', 'classname_' . $term->term_id);
        $location_info = extract_location_from_address_improved($address);
        $site_name = get_bloginfo('name');
        
        add_action('wp_head', function() use ($location_info, $class_name, $site_name) {
            ?>
            <script>
            // ページ読み込み完了後にタイトルを確実に設定
            document.addEventListener('DOMContentLoaded', function() {
                var prefecture = <?php echo json_encode($location_info['prefecture']); ?>;
                var city = <?php echo json_encode($location_info['city']); ?>;
                var className = <?php echo json_encode($class_name); ?>;
                var siteName = <?php echo json_encode($site_name); ?>;
                
                var newTitle = '';
                if (prefecture && city) {
                    newTitle = prefecture + city + ' -' + className + ' | 放課後等デイサービス・児童発達支援の求人　' + siteName;
                } else {
                    newTitle = className + ' | 放課後等デイサービス・児童発達支援の求人　' + siteName;
                }
                
                document.title = newTitle;
                console.log('タイトルを設定しました: ' + newTitle);
            });
            
            // さらに確実にするため、少し遅れても実行
            setTimeout(function() {
                var prefecture = <?php echo json_encode($location_info['prefecture']); ?>;
                var city = <?php echo json_encode($location_info['city']); ?>;
                var className = <?php echo json_encode($class_name); ?>;
                var siteName = <?php echo json_encode($site_name); ?>;
                
                if (prefecture && city) {
                    document.title = prefecture + city + ' -' + className + ' | 放課後等デイサービス・児童発達支援の求人　' + siteName;
                }
            }, 1000);
            </script>
            <?php
        }, 99);
    }
}
add_action('wp', 'force_classroom_title_js');

/**
 * 6. 構造化データも追加
 */
function add_classroom_structured_data_simple() {
    if (is_tax('classname')) {
        $term = get_queried_object();
        $class_name = get_field('claas', 'classname_' . $term->term_id);
        if (!$class_name) {
            $class_name = $term->name;
        }
        
        $address = get_field('addressa', 'classname_' . $term->term_id);
        $phone = get_field('tella', 'classname_' . $term->term_id);
        $website = get_field('web-urla', 'classname_' . $term->term_id);
        $location_info = extract_location_from_address_improved($address);
        
        add_action('wp_head', function() use ($class_name, $address, $phone, $website, $location_info, $term) {
            $structured_data = array(
                '@context' => 'https://schema.org',
                '@type' => 'LocalBusiness',
                'name' => $class_name,
                'url' => get_term_link($term)
            );
            
            if ($location_info['prefecture'] && $location_info['city']) {
                $structured_data['description'] = $location_info['prefecture'] . $location_info['city'] . 'で運動療育を中心とした放課後等デイサービス・児童発達支援を提供';
            } else {
                $structured_data['description'] = '運動療育を中心とした放課後等デイサービス・児童発達支援を提供';
            }
            
            if ($address) {
                $structured_data['address'] = array(
                    '@type' => 'PostalAddress',
                    'streetAddress' => $address,
                    'addressLocality' => $location_info['city'],
                    'addressRegion' => $location_info['prefecture'],
                    'addressCountry' => 'JP'
                );
            }
            
            if ($phone) {
                $structured_data['telephone'] = $phone;
            }
            
            if ($website) {
                $structured_data['url'] = $website;
            }
            
            echo '<script type="application/ld+json">';
            echo json_encode($structured_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            echo '</script>' . "\n";
        }, 10);
    }
}
add_action('wp', 'add_classroom_structured_data_simple');

/**
 * 7. テーマやプラグインによるタイトル上書きを防ぐ
 */
function prevent_title_override() {
    if (is_tax('classname')) {
        // 各種SEOプラグインのフィルターを上書き
        add_filter('wpseo_title', '__return_false', 99);
        add_filter('rank_math/frontend/title', '__return_false', 99);
        add_filter('aioseop_title', '__return_false', 99);
        
        // WordPressのタイトル生成を完全に制御
        add_filter('pre_get_document_title', function() {
            $term = get_queried_object();
            $class_name = get_field('claas', 'classname_' . $term->term_id);
            if (!$class_name) {
                $class_name = $term->name;
            }
            
            $address = get_field('addressa', 'classname_' . $term->term_id);
            $location_info = extract_location_from_address_improved($address);
            $site_name = get_bloginfo('name');
            
            if ($location_info['prefecture'] && $location_info['city']) {
                return $location_info['prefecture'] . $location_info['city'] . ' -' . $class_name . ' | 放課後等デイサービス・児童発達支援の求人　' . $site_name;
            } else {
                return $class_name . ' | 放課後等デイサービス・児童発達支援の求人　' . $site_name;
            }
        }, 99);
    }
}
add_action('wp', 'prevent_title_override', 1);

/**
 * 8. 確認用のショートコード（テスト用）
 */
function classroom_seo_info_shortcode() {
    if (!is_tax('classname')) {
        return '教室ページではありません';
    }
    
    $term = get_queried_object();
    $class_name = get_field('claas', 'classname_' . $term->term_id);
    $address = get_field('addressa', 'classname_' . $term->term_id);
    $location_info = extract_location_from_address_improved($address);
    
    $output = '<div style="background: #f0f0f0; padding: 15px; margin: 15px 0;">';
    $output .= '<h4>教室SEO情報</h4>';
    $output .= '<p><strong>教室名:</strong> ' . ($class_name ? $class_name : '未設定') . '</p>';
    $output .= '<p><strong>住所:</strong> ' . ($address ? $address : '未設定') . '</p>';
    $output .= '<p><strong>都道府県:</strong> ' . ($location_info['prefecture'] ? $location_info['prefecture'] : '抽出失敗') . '</p>';
    $output .= '<p><strong>市区町村:</strong> ' . ($location_info['city'] ? $location_info['city'] : '抽出失敗') . '</p>';
    $output .= '</div>';
    
    return $output;
}
add_shortcode('classroom_seo_info', 'classroom_seo_info_shortcode');







/**
 * 教室ページ専用サイトマップ生成コード（シンプル版）
 * functions.phpに追加してください
 */

/**
 * 1. 教室ページサイトマップ生成
 */
function generate_classroom_pages_sitemap() {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    // classnameタクソノミーの全ての教室ページを取得
    $classroom_terms = get_terms(array(
        'taxonomy' => 'classname',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    
    if (!empty($classroom_terms) && !is_wp_error($classroom_terms)) {
        foreach ($classroom_terms as $term) {
            $term_link = get_term_link($term);
            
            // URL取得が成功した場合のみサイトマップに追加
            if (!is_wp_error($term_link)) {
                $xml .= '  <url>' . "\n";
                $xml .= '    <loc>' . esc_url($term_link) . '</loc>' . "\n";
                $xml .= '    <lastmod>' . date('Y-m-d\TH:i:s+00:00') . '</lastmod>' . "\n";
                $xml .= '    <changefreq>monthly</changefreq>' . "\n";
                $xml .= '    <priority>0.8</priority>' . "\n";
                $xml .= '  </url>' . "\n";
            }
        }
    }
    
    $xml .= '</urlset>';
    return $xml;
}

/**
 * 2. サイトマップアクセス処理
 */
function handle_classroom_pages_request() {
    // /classroom-pages.xml へのアクセスを処理
    if (isset($_SERVER['REQUEST_URI'])) {
        $request_uri = $_SERVER['REQUEST_URI'];
        
        if (strpos($request_uri, '/classroom-pages.xml') !== false) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('Cache-Control: public, max-age=3600');
            
            echo generate_classroom_pages_sitemap();
            exit;
        }
    }
}
add_action('template_redirect', 'handle_classroom_pages_request', 1);

/**
 * 3. WordPressリライトルール追加
 */
function add_classroom_pages_rewrite_rule() {
    add_rewrite_rule(
        '^classroom-pages\.xml$',
        'index.php?classroom_pages_xml=1',
        'top'
    );
}
add_action('init', 'add_classroom_pages_rewrite_rule');

/**
 * 4. クエリ変数追加
 */
function add_classroom_pages_query_var($vars) {
    $vars[] = 'classroom_pages_xml';
    return $vars;
}
add_filter('query_vars', 'add_classroom_pages_query_var');

/**
 * 5. クエリ変数での処理
 */
function handle_classroom_pages_query_var() {
    if (get_query_var('classroom_pages_xml')) {
        header('Content-Type: application/xml; charset=UTF-8');
        header('Cache-Control: public, max-age=3600');
        
        echo generate_classroom_pages_sitemap();
        exit;
    }
}
add_action('template_redirect', 'handle_classroom_pages_query_var');

/**
 * 6. robots.txtに追加
 */
function add_classroom_pages_to_robots($output, $public) {
    if ($public) {
        $output .= "\nSitemap: " . home_url('/classroom-pages.xml') . "\n";
    }
    return $output;
}
add_filter('robots_txt', 'add_classroom_pages_to_robots', 10, 2);

/**
 * 7. 管理画面ページ追加
 */
function add_classroom_pages_admin_menu() {
    add_options_page(
        '教室サイトマップ',
        '教室サイトマップ',
        'manage_options',
        'classroom-sitemap',
        'display_classroom_sitemap_page'
    );
}
add_action('admin_menu', 'add_classroom_pages_admin_menu');

function display_classroom_sitemap_page() {
    if (!current_user_can('manage_options')) {
        wp_die('権限がありません。');
    }
    
    // リライトルール更新
    if (isset($_POST['update_rules'])) {
        flush_rewrite_rules(true);
        echo '<div class="notice notice-success"><p>リライトルールを更新しました。</p></div>';
    }
    
    // サイトマップテスト
    if (isset($_POST['test_sitemap'])) {
        $xml_content = generate_classroom_pages_sitemap();
        $url_count = substr_count($xml_content, '<url>');
        
        echo '<div class="notice notice-info">';
        echo '<p><strong>テスト結果:</strong> ' . $url_count . '個の教室ページURLが生成されました。</p>';
        echo '</div>';
    }
    
    $site_url = home_url();
    $classroom_count = wp_count_terms('classname', array('hide_empty' => false));
    
    ?>
    <div class="wrap">
        <h1>教室ページサイトマップ</h1>
        
        <div class="card">
            <h2>サイトマップ情報</h2>
            <p><strong>教室数:</strong> <?php echo is_wp_error($classroom_count) ? '取得エラー' : $classroom_count; ?>件</p>
            <p><strong>サイトマップURL:</strong></p>
            <p>
                <code><?php echo $site_url; ?>/classroom-pages.xml</code>
                <a href="<?php echo $site_url; ?>/classroom-pages.xml" target="_blank" class="button button-small">確認</a>
            </p>
        </div>
        
        <div class="card">
            <h2>管理</h2>
            <form method="post">
                <p>
                    <input type="submit" name="test_sitemap" class="button button-secondary" value="サイトマップをテスト" />
                    <input type="submit" name="update_rules" class="button button-primary" value="リライトルール更新" />
                </p>
            </form>
        </div>
        
        <div class="card">
            <h2>Google Search Console登録用URL</h2>
            <p>以下のURLをSearch Consoleに登録してください：</p>
            <p><code><?php echo $site_url; ?>/classroom-pages.xml</code></p>
        </div>
        
        <div class="card">
            <h2>教室ページ例</h2>
            <?php
            $sample_terms = get_terms(array(
                'taxonomy' => 'classname',
                'hide_empty' => false,
                'number' => 5
            ));
            
            if (!empty($sample_terms) && !is_wp_error($sample_terms)) {
                echo '<ul>';
                foreach ($sample_terms as $term) {
                    $term_link = get_term_link($term);
                    if (!is_wp_error($term_link)) {
                        echo '<li><a href="' . esc_url($term_link) . '" target="_blank">' . esc_html($term->name) . '</a></li>';
                    }
                }
                echo '</ul>';
            } else {
                echo '<p>教室ページが見つかりません。</p>';
            }
            ?>
        </div>
    </div>
    
    <style>
    .card {
        background: #fff;
        border: 1px solid #c3c4c7;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        padding: 20px;
        margin-bottom: 20px;
    }
    .card h2 {
        margin-top: 0;
    }
    code {
        background: #f1f1f1;
        padding: 2px 5px;
        border-radius: 3px;
    }
    </style>
    <?php
}

/**
 * 8. 初期設定
 */
function initialize_classroom_pages_sitemap() {
    if (!get_option('classroom_pages_sitemap_init')) {
        flush_rewrite_rules(true);
        update_option('classroom_pages_sitemap_init', true);
    }
}
add_action('init', 'initialize_classroom_pages_sitemap', 999);






// WP-Members任意項目アコーディオン化（全ページ対応）
function wpmembers_accordion_init() {
    ?>
    <style>
    /* WP-Membersアコーディオンのスタイル */
    .wpmem-optional-accordion {
        margin: 30px 0;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        overflow: hidden;
        background: #fff;
    }

    .wpmem-accordion-header {
        background: #FFF5E6;
        padding: 15px 20px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #e0e0e0;
        transition: all 0.3s ease;
        user-select: none;
    }

    .wpmem-accordion-header:hover {
        background: #e9ecef;
    }

    .wpmem-accordion-header.active {
        background: #e3f2fd;
        border-bottom-color: #2196f3;
    }

    .wpmem-accordion-title {
        font-weight: 500;
        color: #333;
        margin: 0;
        display: flex;
        align-items: center;
        font-size: 16px;
    }

    .wpmem-optional-badge {
        background: #6c757d;
        color: white;
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 12px;
        margin-left: 10px;
        font-weight: normal;
    }

    .wpmem-accordion-icon {
        font-size: 18px;
        color: #666;
        transition: transform 0.3s ease;
        font-weight: bold;
    }

    .wpmem-accordion-header.active .wpmem-accordion-icon {
        transform: rotate(180deg);
    }

    .wpmem-accordion-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.4s ease;
        background: white;
    }

    .wpmem-accordion-content.active {
        max-height: 2000px;
    }

    .wpmem-accordion-fields {
        padding: 20px;
    }

    /* 任意項目を非表示にする */
    .wpmem-optional-field {
        display: none;
    }

    /* アコーディオンが開いている時のみ表示 */
    .wpmem-accordion-content.active .wpmem-optional-field {
        display: block;
        margin-bottom: 20px;
    }

    /* フォームのスタイル調整 */
    .wpmem-accordion-fields .text,
    .wpmem-accordion-fields .select,
    .wpmem-accordion-fields .number {
        display: block;
        font-weight: 500;
        color: #333;
        margin-bottom: 5px;
    }

    .wpmem-accordion-fields .textbox,
    .wpmem-accordion-fields .dropdown {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        box-sizing: border-box;
    }

    .wpmem-accordion-fields .textbox:focus,
    .wpmem-accordion-fields .dropdown:focus {
        outline: none;
        border-color: #3498db;
        box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
    }

    /* レスポンシブ対応 */
    @media (max-width: 768px) {
        .wpmem-accordion-header {
            padding: 12px 15px;
        }
        
        .wpmem-accordion-fields {
            padding: 15px;
        }
        
        .wpmem-accordion-title {
            font-size: 14px;
        }
    }
    </style>

    <script>
    // jQueryまたはVanilla JSで対応
    (function() {
        // jQueryが利用可能かチェック
        function initAccordion() {
            if (typeof jQuery !== 'undefined') {
                initWithJQuery();
            } else {
                initWithVanillaJS();
            }
        }

        // jQuery版
        function initWithJQuery() {
            jQuery(document).ready(function($) {
                createAccordion($);
                
                // 動的コンテンツ対応（MutationObserver使用）
                if (window.MutationObserver) {
                    const observer = new MutationObserver(function(mutations) {
                        let shouldCheck = false;
                        mutations.forEach(function(mutation) {
                            if (mutation.addedNodes.length > 0) {
                                shouldCheck = true;
                            }
                        });
                        
                        if (shouldCheck && $('.wpmem-optional-accordion').length === 0) {
                            setTimeout(function() {
                                createAccordion($);
                            }, 100);
                        }
                    });
                    
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                }
            });
        }

        // Vanilla JS版
        function initWithVanillaJS() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    createAccordionVanilla();
                });
            } else {
                createAccordionVanilla();
            }
            
            // 動的コンテンツ対応
            if (window.MutationObserver) {
                const observer = new MutationObserver(function(mutations) {
                    let shouldCheck = false;
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length > 0) {
                            shouldCheck = true;
                        }
                    });
                    
                    if (shouldCheck && !document.querySelector('.wpmem-optional-accordion')) {
                        setTimeout(function() {
                            createAccordionVanilla();
                        }, 100);
                    }
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        }

        // jQuery版アコーディオン作成
        function createAccordion($) {
            const optionalFields = [
                'tel', 'age', 'seibetu', 'qualification', 
                'employment', 'Desiredtime', 'years'
            ];

            let optionalElements = [];
            
            optionalFields.forEach(function(fieldId) {
                const label = $('label[for="' + fieldId + '"]');
                const input = $('#' + fieldId);
                const wrapper = input.closest('.div_text, .div_select, .div_number');
                
                if (label.length && (input.length || wrapper.length)) {
                    const fieldGroup = $('<div class="wpmem-optional-field"></div>');
                    
                    if (wrapper.length) {
                        fieldGroup.append(label.clone()).append(wrapper.clone());
                        label.remove();
                        wrapper.remove();
                    } else {
                        fieldGroup.append(label.clone()).append(input.clone());
                        label.remove();
                        input.remove();
                    }
                    
                    optionalElements.push(fieldGroup);
                }
            });

            if (optionalElements.length === 0) return;

            const accordion = $(`
                <div class="wpmem-optional-accordion">
                    <div class="wpmem-accordion-header">
                        <div class="wpmem-accordion-title">
                            詳細情報の入力
                            <span class="wpmem-optional-badge">任意</span>
                        </div>
                        <span class="wpmem-accordion-icon">▼</span>
                    </div>
                    <div class="wpmem-accordion-content">
                        <div class="wpmem-accordion-fields"></div>
                    </div>
                </div>
            `);

            const accordionFields = accordion.find('.wpmem-accordion-fields');
            optionalElements.forEach(function(element) {
                accordionFields.append(element);
            });

            // 挿入位置を決定
            const insertAfter = $('#jobtype').closest('.div_select') || 
                              $('label[for="jobtype"]').next() ||
                              $('input[name="jobtype"]').closest('div') ||
                              $('fieldset').children().last();
            
            if (insertAfter.length) {
                insertAfter.after(accordion);
            } else {
                $('fieldset').append(accordion);
            }

            // クリックイベント
            accordion.find('.wpmem-accordion-header').on('click', function() {
                const header = $(this);
                const content = header.next('.wpmem-accordion-content');
                
                header.toggleClass('active');
                content.toggleClass('active');
                
                if (content.hasClass('active')) {
                    content.css('max-height', content[0].scrollHeight + 'px');
                } else {
                    content.css('max-height', '0');
                }
            });
        }

        // Vanilla JS版アコーディオン作成
        function createAccordionVanilla() {
            const optionalFields = [
                'tel', 'age', 'seibetu', 'qualification', 
                'employment', 'Desiredtime', 'years'
            ];

            let optionalElements = [];
            
            optionalFields.forEach(function(fieldId) {
                const label = document.querySelector('label[for="' + fieldId + '"]');
                const input = document.getElementById(fieldId);
                
                if (label && input) {
                    const wrapper = input.closest('.div_text') || 
                                  input.closest('.div_select') || 
                                  input.closest('.div_number');
                    
                    const fieldGroup = document.createElement('div');
                    fieldGroup.className = 'wpmem-optional-field';
                    
                    if (wrapper) {
                        fieldGroup.appendChild(label.cloneNode(true));
                        fieldGroup.appendChild(wrapper.cloneNode(true));
                        label.remove();
                        wrapper.remove();
                    } else {
                        fieldGroup.appendChild(label.cloneNode(true));
                        fieldGroup.appendChild(input.cloneNode(true));
                        label.remove();
                        input.remove();
                    }
                    
                    optionalElements.push(fieldGroup);
                }
            });

            if (optionalElements.length === 0) return;

            const accordion = document.createElement('div');
            accordion.className = 'wpmem-optional-accordion';
            accordion.innerHTML = `
                <div class="wpmem-accordion-header">
                    <div class="wpmem-accordion-title">
                        詳細情報の入力
                        <span class="wpmem-optional-badge">任意</span>
                    </div>
                    <span class="wpmem-accordion-icon">▼</span>
                </div>
                <div class="wpmem-accordion-content">
                    <div class="wpmem-accordion-fields"></div>
                </div>
            `;

            const accordionFields = accordion.querySelector('.wpmem-accordion-fields');
            optionalElements.forEach(function(element) {
                accordionFields.appendChild(element);
            });

            // 挿入位置を決定
            const jobTypeElement = document.getElementById('jobtype');
            const insertAfter = jobTypeElement ? 
                               (jobTypeElement.closest('.div_select') || jobTypeElement.parentNode) :
                               document.querySelector('fieldset');
            
            if (insertAfter && insertAfter.parentNode) {
                insertAfter.parentNode.insertBefore(accordion, insertAfter.nextSibling);
            } else {
                document.querySelector('fieldset').appendChild(accordion);
            }

            // クリックイベント
            accordion.querySelector('.wpmem-accordion-header').addEventListener('click', function() {
                const header = this;
                const content = header.nextElementSibling;
                
                header.classList.toggle('active');
                content.classList.toggle('active');
                
                if (content.classList.contains('active')) {
                    content.style.maxHeight = content.scrollHeight + 'px';
                } else {
                    content.style.maxHeight = '0';
                }
            });
        }

        // 初期化実行
        initAccordion();
    })();
    </script>
    <?php
}
add_action('wp_footer', 'wpmembers_accordion_init');




// WP-Members郵便番号自動住所取得機能（address3対応）
function wpmembers_zipcode_auto_address() {
    ?>
    <style>
    /* 郵便番号関連のスタイル */
    .zipcode-container {
        position: relative;
    }

    .zipcode-status {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 12px;
        pointer-events: none;
        z-index: 10;
    }

    .zipcode-error {
        color: #e74c3c;
    }

    .address-auto-filled {
        background-color: #e8f5e8 !important;
        border-color: #27ae60 !important;
        transition: all 0.3s ease;
    }

    .zipcode-help {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
        font-style: italic;
    }

    /* 住所項目のアニメーション */
    .address-filling {
        animation: addressFill 0.5s ease-in-out;
    }

    @keyframes addressFill {
        0% { background-color: #fff; }
        50% { background-color: #e8f5e8; }
        100% { background-color: #fff; }
    }
    </style>

    <script>
    (function() {
        // 郵便番号APIのURL
        const ZIPCODE_API_URL = 'https://zipcloud.ibsnet.co.jp/api/search';

        function initZipcodeFeature() {
            if (typeof jQuery !== 'undefined') {
                initZipcodeWithJQuery();
            } else {
                initZipcodeWithVanillaJS();
            }
        }

        // jQuery版
        function initZipcodeWithJQuery() {
            jQuery(document).ready(function($) {
                setupZipcodeFeature($);
                
                // 動的コンテンツ対応
                if (window.MutationObserver) {
                    const observer = new MutationObserver(function(mutations) {
                        let shouldCheck = false;
                        mutations.forEach(function(mutation) {
                            if (mutation.addedNodes.length > 0) {
                                shouldCheck = true;
                            }
                        });
                        
                        if (shouldCheck) {
                            setTimeout(function() {
                                setupZipcodeFeature($);
                            }, 100);
                        }
                    });
                    
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                }
            });
        }

        // Vanilla JS版
        function initZipcodeWithVanillaJS() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setupZipcodeFeatureVanilla();
                });
            } else {
                setupZipcodeFeatureVanilla();
            }
            
            if (window.MutationObserver) {
                const observer = new MutationObserver(function(mutations) {
                    let shouldCheck = false;
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length > 0) {
                            shouldCheck = true;
                        }
                    });
                    
                    if (shouldCheck) {
                        setTimeout(function() {
                            setupZipcodeFeatureVanilla();
                        }, 100);
                    }
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        }

        // jQuery版郵便番号機能セットアップ
        function setupZipcodeFeature($) {
            const postcodeInput = $('#postcode');
            if (postcodeInput.length === 0 || postcodeInput.data('zipcode-setup')) return;
            
            postcodeInput.data('zipcode-setup', true);
            
            // 郵便番号入力欄を相対位置のコンテナで囲む
            if (!postcodeInput.parent().hasClass('zipcode-container')) {
                postcodeInput.wrap('<div class="zipcode-container"></div>');
                postcodeInput.after('<div class="zipcode-status"></div>');
                postcodeInput.after('<div class="zipcode-help">ハイフンなしで7桁入力すると住所が自動取得されます</div>');
            }
            
            const statusElement = postcodeInput.siblings('.zipcode-status');
            const prefInput = $('#prefectures');
            const cityInput = $('#municipalities');
            const streetInput = $('#streetaddress');
            
            // 入力イベントリスナー
            postcodeInput.on('input', function() {
                const zipcode = $(this).val().replace(/[^\d]/g, '');
                
                if (zipcode.length === 7) {
                    fetchAddressJQuery(zipcode, statusElement, prefInput, cityInput, streetInput, $);
                } else {
                    statusElement.html('');
                    resetAddressStyle([prefInput, cityInput, streetInput], $);
                }
            });

            // フォーカスアウト時にもチェック
            postcodeInput.on('blur', function() {
                const zipcode = $(this).val().replace(/[^\d]/g, '');
                if (zipcode.length === 7) {
                    fetchAddressJQuery(zipcode, statusElement, prefInput, cityInput, streetInput, $);
                }
            });
        }

        // Vanilla JS版郵便番号機能セットアップ
        function setupZipcodeFeatureVanilla() {
            const postcodeInput = document.getElementById('postcode');
            if (!postcodeInput || postcodeInput.dataset.zipcodeSetup) return;
            
            postcodeInput.dataset.zipcodeSetup = 'true';
            
            // コンテナで囲む
            if (!postcodeInput.parentElement.classList.contains('zipcode-container')) {
                const container = document.createElement('div');
                container.className = 'zipcode-container';
                postcodeInput.parentNode.insertBefore(container, postcodeInput);
                container.appendChild(postcodeInput);
                
                const statusElement = document.createElement('div');
                statusElement.className = 'zipcode-status';
                container.appendChild(statusElement);
                
                const helpElement = document.createElement('div');
                helpElement.className = 'zipcode-help';
                helpElement.textContent = 'ハイフンなしで7桁入力すると住所が自動取得されます';
                container.appendChild(helpElement);
            }
            
            const statusElement = postcodeInput.parentElement.querySelector('.zipcode-status');
            const prefInput = document.getElementById('prefectures');
            const cityInput = document.getElementById('municipalities');
            const streetInput = document.getElementById('streetaddress');
            
            // イベントリスナー
            postcodeInput.addEventListener('input', function() {
                const zipcode = this.value.replace(/[^\d]/g, '');
                
                if (zipcode.length === 7) {
                    fetchAddressVanilla(zipcode, statusElement, prefInput, cityInput, streetInput);
                } else {
                    statusElement.innerHTML = '';
                    resetAddressStyleVanilla([prefInput, cityInput, streetInput]);
                }
            });

            postcodeInput.addEventListener('blur', function() {
                const zipcode = this.value.replace(/[^\d]/g, '');
                if (zipcode.length === 7) {
                    fetchAddressVanilla(zipcode, statusElement, prefInput, cityInput, streetInput);
                }
            });
        }

        // jQuery版住所取得（address3対応）
        function fetchAddressJQuery(zipcode, statusElement, prefInput, cityInput, streetInput, $) {
            // 取得中は何も表示しない
            
            $.ajax({
                url: ZIPCODE_API_URL,
                type: 'GET',
                dataType: 'jsonp',
                data: { zipcode: zipcode },
                timeout: 8000,
                success: function(data) {
                    if (data.status === 200 && data.results && data.results.length > 0) {
                        const result = data.results[0];
                        
                        // 住所を設定
                        prefInput.val(result.address1);          // 都道府県
                        cityInput.val(result.address2);          // 市区町村
                        
                        // address3があれば番地欄に設定、なければ空のまま
                        if (result.address3 && result.address3.trim() !== '') {
                            streetInput.val(result.address3);    // 町名・番地
                        }
                        
                        // 成功時も何も表示しない
                        
                        // 成功アニメーション
                        const filledInputs = [prefInput, cityInput];
                        if (result.address3 && result.address3.trim() !== '') {
                            filledInputs.push(streetInput);
                        }
                        
                        filledInputs.forEach(function(input) {
                            input.addClass('address-auto-filled address-filling');
                            setTimeout(function() {
                                input.removeClass('address-auto-filled address-filling');
                            }, 3000);
                        });
                        
                        // 番地欄にフォーカス（address3がない場合や追記が必要な場合）
                        setTimeout(function() {
                            streetInput.focus();
                        }, 500);
                        
                    } else {
                        statusElement.html('<span class="zipcode-error">✗ 住所が見つかりません</span>');
                        setTimeout(function() {
                            statusElement.html('');
                        }, 4000);
                    }
                },
                error: function() {
                    statusElement.html('<span class="zipcode-error">✗ 住所取得に失敗しました</span>');
                    setTimeout(function() {
                        statusElement.html('');
                    }, 4000);
                }
            });
        }

        // Vanilla JS版住所取得（address3対応）
        function fetchAddressVanilla(zipcode, statusElement, prefInput, cityInput, streetInput) {
            // 取得中は何も表示しない
            
            // JSONP リクエスト
            const script = document.createElement('script');
            const callbackName = 'zipcodeCallback' + Date.now();
            
            window[callbackName] = function(data) {
                if (data.status === 200 && data.results && data.results.length > 0) {
                    const result = data.results[0];
                    
                    // 住所を設定
                    prefInput.value = result.address1;          // 都道府県
                    cityInput.value = result.address2;          // 市区町村
                    
                    // address3があれば番地欄に設定
                    if (result.address3 && result.address3.trim() !== '') {
                        streetInput.value = result.address3;    // 町名・番地
                    }
                    
                    // 成功時も何も表示しない
                    
                    // 成功アニメーション
                    const filledInputs = [prefInput, cityInput];
                    if (result.address3 && result.address3.trim() !== '') {
                        filledInputs.push(streetInput);
                    }
                    
                    filledInputs.forEach(function(input) {
                        input.classList.add('address-auto-filled', 'address-filling');
                        setTimeout(function() {
                            input.classList.remove('address-auto-filled', 'address-filling');
                        }, 3000);
                    });
                    
                    // 番地欄にフォーカス
                    setTimeout(function() {
                        streetInput.focus();
                    }, 500);
                    
                } else {
                    statusElement.innerHTML = '<span class="zipcode-error">✗ 住所が見つかりません</span>';
                    setTimeout(function() {
                        statusElement.innerHTML = '';
                    }, 4000);
                }
                
                cleanup();
            };
            
            function cleanup() {
                if (script.parentNode) {
                    document.head.removeChild(script);
                }
                delete window[callbackName];
            }
            
            script.src = ZIPCODE_API_URL + '?zipcode=' + zipcode + '&callback=' + callbackName;
            script.onerror = function() {
                statusElement.innerHTML = '<span class="zipcode-error">✗ 住所取得に失敗しました</span>';
                setTimeout(function() {
                    statusElement.innerHTML = '';
                }, 4000);
                cleanup();
            };
            
            document.head.appendChild(script);
            
            // タイムアウト処理
            setTimeout(function() {
                if (window[callbackName]) {
                    statusElement.innerHTML = '<span class="zipcode-error">✗ 取得がタイムアウトしました</span>';
                    setTimeout(function() {
                        statusElement.innerHTML = '';
                    }, 4000);
                    cleanup();
                }
            }, 8000);
        }

        // スタイルリセット関数
        function resetAddressStyle(inputs, $) {
            inputs.forEach(function(input) {
                if ($ && input && input.length) {
                    input.removeClass('address-auto-filled address-filling');
                }
            });
        }

        function resetAddressStyleVanilla(inputs) {
            inputs.forEach(function(input) {
                if (input) {
                    input.classList.remove('address-auto-filled', 'address-filling');
                }
            });
        }

        // 初期化実行
        initZipcodeFeature();
    })();
    </script>
    <?php
}
add_action('wp_footer', 'wpmembers_zipcode_auto_address');