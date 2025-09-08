<?php
/**
 * 会員登録ページのテンプレート
 * Template Name: 会員登録ページ
 */
get_header();

// ログイン済みユーザーの場合はマイページにリダイレクト
if (is_user_logged_in()) {
    wp_redirect(home_url('/members/'));
    exit;
}

// 以下は未ログインユーザー向けの処理
// 会員登録のメリットを配列で定義
$benefits = [
    ['icon' => 'fa-search', 'title' => '求人情報の検索', 'description' => '希望条件に合った求人をカンタンに検索できます'],
    ['icon' => 'fa-bell', 'title' => '新着求人お知らせ', 'description' => '希望条件に合った新着求人をメールでお知らせします'],
    ['icon' => 'fa-heart', 'title' => 'お気に入り機能', 'description' => '気になる求人を保存して後で確認できます'],
];
?>
<main class="registration-page">
  <div class="container">
    <div class="row">
      <!-- 左カラム：登録フォーム -->
      <div class="col-md-8">
        <div class="auth-container">
          <div class="auth-header">
            <h1 class="auth-title">会員登録<span class="registration-free">無料</span></h1>
            <p class="auth-description">アカウントを作成して、すべての機能をご利用ください</p>
          </div>
          
          <!-- 登録進行状況を表示 -->
          <div class="registration-progress">
            <div class="progress-step active">
              <span class="step-number">1</span>
              <span class="step-label">情報入力</span>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
              <span class="step-number">2</span>
              <span class="step-label">メール確認</span>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
              <span class="step-number">3</span>
              <span class="step-label">登録完了</span>
            </div>
          </div>
          
          <!-- 入力フォーム -->
          <div class="auth-form">
            <?php echo do_shortcode('[wpmem_form register]'); ?>
          </div>
          
          <!-- 既存ユーザー向けリンク -->
          <div class="auth-footer">
            <p>すでにアカウントをお持ちですか？ <a href="/login" class="login-link">ログイン</a></p>
          </div>
        </div>
      </div>
      
      <!-- 右カラム：会員登録のメリット -->
      <div class="col-md-4">
        <div class="benefits-container">
          <h2 class="benefits-title">会員登録のメリット</h2>
          
          <div class="benefits-list">
            <?php foreach ($benefits as $benefit) : ?>
            <div class="benefit-item">
              <div class="benefit-icon">
                <i class="fas <?php echo esc_attr($benefit['icon']); ?>"></i>
              </div>
              <div class="benefit-content">
                <h3 class="benefit-title"><?php echo esc_html($benefit['title']); ?></h3>
                <p class="benefit-description"><?php echo esc_html($benefit['description']); ?></p>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          
          <!-- 個人情報保護方針へのリンク -->
          <div class="privacy-note">
            <i class="fas fa-shield-alt"></i><p>お客様の個人情報は<a href="/privacy" target="_blank">個人情報保護方針</a>に基づき適切に管理いたします。</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>


<?php 
// Font Awesome を読み込む
if (!wp_script_is('font-awesome', 'enqueued')) {
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
}
get_footer(); 
?>