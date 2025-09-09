<?php
/**
 * タクソノミーアーカイブテンプレート
 * すべてのタクソノミーアーカイブページに適用されます
 */
get_header();

// 現在のタクソノミー情報を取得
$term = get_queried_object();
$taxonomy = get_taxonomy($term->taxonomy);
?>
<!DOCTYPE html>

<style>
.recruitment-header-banner {
    background: linear-gradient(135deg, #ffd966, #f6b73c);
    height: 80px;
    width: 100vw;
    margin-left: calc(50% - 50vw);
    margin-right: calc(50% - 50vw);
}

.recruitment-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 40px 20px;
    background-color: #fff;
    min-height: calc(100vh - 80px);
}

.recruitment-main-title {
    text-align: center;
    font-size: 28px;
    font-weight: bold;
    color: #333;
    margin-bottom: 50px;
    letter-spacing: 1px;
}

.recruitment-intro-text {
    text-align: center;
    margin-bottom: 60px;
    line-height: 1.8;
}

.recruitment-company-name {
    font-weight: bold;
    color: #333;
}

.recruitment-info-section {
    margin-bottom: 60px;
}

.recruitment-info-row {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.recruitment-info-label {
    color: #f6b73c;
    font-weight: bold;
    min-width: 120px;
    margin-right: 20px;
}

.recruitment-info-value {
    color: #333;
}

.recruitment-info-value a {
    color: #333;
    text-decoration: none;
}

.recruitment-info-value a:hover {
    text-decoration: underline;
}

.recruitment-section-title {
    text-align: center;
    font-size: 24px;
    font-weight: bold;
    color: #333;
    margin-bottom: 30px;
    position: relative;
}

.recruitment-section-title::after {
    content: '';
    display: block;
    width: 60px;
    height: 4px;
    background-color: #f6b73c;
    margin: 15px auto 0;
}

.recruitment-job-listings {
    background-color: #fff;
    border: 2px solid #f6b73c;
    border-radius: 10px;
    padding: 30px;
    margin-bottom: 60px;
}

.recruitment-job-item {
    display: flex;
    align-items: center;
    padding: 8px 0;
    font-size: 16px;
    color: #333;
    position: relative;
    padding-left: 20px;
}

.recruitment-job-item::before {
    content: '●';
    color: #f6b73c;
    font-weight: bold;
    font-size: 20px;
    position: absolute;
    left: 0;
    top: 6px;
}

.recruitment-job-link {
    display: flex;
    align-items: center;
    text-decoration: none;
    color: inherit;
    width: 100%;
}

.recruitment-job-link:hover {
    color: #f6b73c;
    text-decoration: none;
}

.recruitment-job-link:hover .recruitment-job-category {
    color: #f6b73c;
}

.recruitment-job-category {
    font-weight: bold;
    margin-right: 8px;
    transition: color 0.3s ease;
}

.recruitment-job-position {
    margin-left: 0;
    margin-right: 0;
    transition: color 0.3s ease;
font-weight: bold;
}

.recruitment-contact-section {
    text-align: center;
}

.recruitment-contact-title {
    font-size: 24px;
    font-weight: bold;
    color: #333;
    margin-bottom: 30px;
    position: relative;
}

.recruitment-contact-title::after {
    content: '';
    display: block;
    width: 60px;
    height: 4px;
    background-color: #f6b73c;
    margin: 15px auto 0;
}

@media (max-width: 768px) {
    .recruitment-container {
        padding: 30px 0px;
    }

    .recruitment-main-title {
        font-size: 24px;
        margin-bottom: 30px;
    }

    .recruitment-intro-text {
        font-size: 14px;
        margin-bottom: 40px;
    }

    .recruitment-info-row {
        flex-direction: column;
        align-items: flex-start;
        margin-bottom: 20px;
    }

    .recruitment-info-label {
        margin-bottom: 5px;
        margin-right: 0;
    }

    .recruitment-section-title {
        font-size: 20px;
    }

    .recruitment-job-listings {
        padding: 20px;
    }

    .recruitment-job-item {
        flex-direction: column;
        align-items: flex-start;
        padding: 15px 0 15px 20px;
        font-size: 14px;
    }

    .recruitment-job-item::before {
        top: 13px;
    }

    .recruitment-job-link {
        flex-direction: column;
        align-items: flex-start;
    }

    .recruitment-job-category {
        margin-bottom: 5px;
        margin-right: 0;
        min-width: auto;
    }

    .recruitment-contact-title {
        font-size: 20px;
    }
}
</style>

<div class="recruitment-header-banner"></div>

<div class="recruitment-container">
    <?php 
    // ACFフィールドから値を取得
    $class_name = get_field('claas') ? get_field('claas') : 'こどもプラス教室';
    $address = get_field('addressa');
    $phone = get_field('tella');
    $website = get_field('web-urla');
    ?>

    <h1 class="recruitment-main-title"><?php echo esc_html($class_name); ?>の<br>求人情報一覧</h1>
    
    <div class="recruitment-intro-text">
        <span class="recruitment-company-name"><?php echo esc_html($class_name); ?></span>（<?php echo esc_html($address); ?>）の求人募集中！<br>
        一緒に働いてくださる方を募集しております！みなさまのご応募、お問い合わせお待ちしております！
    </div>

    <div class="recruitment-info-section">
        <?php if($address): ?>
        <div class="recruitment-info-row">
            <span class="recruitment-info-label">住所：</span>
            <span class="recruitment-info-value"><?php echo esc_html($address); ?></span>
        </div>
        <?php endif; ?>

        <?php if($phone): ?>
        <div class="recruitment-info-row">
            <span class="recruitment-info-label">電話番号：</span>
            <span class="recruitment-info-value"><?php echo esc_html($phone); ?></span>
        </div>
        <?php endif; ?>

        <?php if($website): ?>
        <div class="recruitment-info-row">
            <span class="recruitment-info-label">WEBサイト：</span>
            <span class="recruitment-info-value">
                <a href="<?php echo esc_url($website); ?>" target="_blank">
                    <?php echo esc_html($website); ?>
                </a>
            </span>
        </div>
        <?php endif; ?>
    </div>

    <?php 
    // 現在のページのACF「claas」から教室名を取得
    $current_class_name = get_field('claas');
    
    if ($current_class_name) :
        // 投稿タイトルから教室名を含む求人を検索
        $job_args = array(
            'post_type' => 'job',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            's' => $current_class_name, // タイトルから検索
            'orderby' => 'menu_order date',
            'order' => 'ASC'
        );
        
        $job_posts = get_posts($job_args);
        
        if ($job_posts) :
            // 求人がある場合のみセクションタイトルと一覧を表示
            ?>
            <div class="recruitment-section">
                <h2 class="recruitment-section-title">求人内容・ご応募は各詳細から</h2>
                
                <div class="recruitment-job-listings">
                    <?php
                    foreach ($job_posts as $job_post) :
                        $job_permalink = get_permalink($job_post->ID);
                        
                        // タクソノミーjob_positionを取得
                        $job_positions = get_the_terms($job_post->ID, 'job_position');
                        $position_names = array();
                        if ($job_positions && !is_wp_error($job_positions)) {
                            foreach ($job_positions as $position) {
                                $position_names[] = $position->name;
                            }
                        }
                        
                        // タクソノミーjob_typeを取得
                        $job_types = get_the_terms($job_post->ID, 'job_type');
                        $type_names = array();
                        if ($job_types && !is_wp_error($job_types)) {
                            foreach ($job_types as $type) {
                                $type_names[] = $type->name;
                            }
                        }
                        
                        // 表示テキストを作成
                        $display_text = $current_class_name; // 先頭に教室名を追加
                        
                        if (!empty($position_names)) {
                            $display_text .= '　' . implode('・', $position_names);
                        }
                        if (!empty($type_names)) {
                            $display_text .= '（' . implode('・', $type_names) . '）';
                        }
                        
                        // タクソノミーが設定されていない場合は投稿タイトルを使用
                        if (empty($position_names) && empty($type_names)) {
                            $display_text = $job_post->post_title;
                        }
                        ?>
                        <div class="recruitment-job-item">
                            <a href="<?php echo esc_url($job_permalink); ?>" class="recruitment-job-link">
                                <span class="recruitment-job-position"><?php echo esc_html($display_text); ?></span>
                            </a>
                        </div>
                        <?php
                    endforeach;
                    ?>
                </div>
            </div>
            <?php
        else :
            // 該当する求人投稿が見つからない場合の代替メッセージ
            ?>
            <div class="recruitment-section">
                <div class="recruitment-job-listings">
                    <div class="recruitment-job-item">
                        <span class="recruitment-job-position"><?php echo esc_html($current_class_name); ?>の求人詳細情報は下記のフォームよりお問い合わせください。</span>
                    </div>
                </div>
            </div>
            <?php
        endif;
    else :
        // claasフィールドが設定されていない場合のメッセージ
        ?>
        <div class="recruitment-section">
            <div class="recruitment-job-listings">
                <div class="recruitment-job-item">
                    <span class="recruitment-job-position">教室情報が設定されていません</span>
                </div>
            </div>
        </div>
        <?php
    endif;
    ?>

    <div class="recruitment-contact-section">
    <?php echo do_shortcode('[contact-form-7 id="09d4612" title="お問い合わせ"]'); ?>

    </div>
</div>
 <!-- 募集セクション -->
  <section class="recruitment">
    <div class="container">
      <h2 class="recruitment-title">こどもプラスで一緒に働きませんか？</h2>
      <p class="recruitment-text">こどもたちの成長を支える喜びを一緒に感じましょう。<br>あなたのスキルや経験を活かせる場所がきっと見つかります。</p>
      <a href="/jobs" class="apply-button">募集中の求人を見る</a>
    </div>
  </section>
<?php get_footer(); ?>