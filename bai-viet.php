<?php
require_once __DIR__.'/site/bootstrap.php';
$slug=is_string($_GET['slug']??null)?$_GET['slug']:'';
$articleContent=$content['posts'][$slug]??null;
if(!$articleContent || (!$articleContent['enabled'] && !(defined('GHE_PREVIEW') && GHE_PREVIEW))) { http_response_code(404); header('X-Robots-Tag: noindex'); exit('Không tìm thấy bài viết.'); }
$publicPages['article']=['file'=>'bai-viet.php?slug='.rawurlencode($slug),'label'=>$articleContent['title'],'title'=>$articleContent['title'].' — Ghé Đà Lạt','description'=>$articleContent['excerpt'],'navigation'=>false];
$pageKey='article'; require __DIR__.'/site/header.php';
?>
<article class="wrap"><header class="page-intro"><p class="eyebrow">CHUYỆN Ở GHÉ</p><h1><?= ghe_h($articleContent['title']) ?></h1><p class="lead"><?= ghe_h($articleContent['excerpt']) ?></p><?php if($articleContent['published_at']): ?><p class="fine-print">Ghé · <time datetime="<?= ghe_h($articleContent['published_at']) ?>"><?= ghe_h((new DateTimeImmutable($articleContent['published_at']))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('d/m/Y')) ?></time></p><?php endif; ?></header><div class="prose section"><?php ghe_paragraphs($articleContent['body']); ?></div></article>
<?php require __DIR__.'/site/footer.php'; ?>
