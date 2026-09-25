<?php

/**
 * Section: news and announcements.
 *
 * @package  Templates.lkim3
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Component\Content\Site\Helper\RouteHelper;
?>
<div class="section-head">
    <div>
        <div class="mark"><span aria-hidden="true"></span><small><?php echo Text::_('TPL_LKIM3_NEWS_EYEBROW'); ?></small></div>
        <h2><?php echo Text::_('TPL_LKIM3_NEWS_TITLE'); ?></h2>
        <p><?php echo Text::_('TPL_LKIM3_NEWS_LEAD'); ?></p>
    </div>
    <a href="<?php echo $a($link($this->params->get('newsMoreLink'))); ?>" class="link-more">
        <?php echo Text::_('TPL_LKIM3_NEWS_MORE'); ?><?php echo $svgArrow; ?>
    </a>
</div>

<?php if ($fromModules('srcNews', 'announcements')) : ?>
    <jdoc:include type="modules" name="announcements" style="none" />
<?php else : ?>
    <div class="news-grid">
        <?php foreach ($news as $i => $row) : ?>
            <?php
            $images = json_decode((string) $row->images);
            $thumb  = $images->image_intro ?? $images->image_fulltext ?? '';
            $thumb  = $thumb ? $asset(HTMLHelper::cleanImageURL($thumb)->url) : $img($newsFallback[$i % 3]);
            $route  = RouteHelper::getArticleRoute($row->id . ':' . $row->alias, $row->catid . ':' . $row->category_alias);
            ?>
            <article class="news-card <?php echo $newsAccents[$i % 3]; ?>">
                <div class="news-thumb">
                    <span class="news-tag"><?php echo $e($row->category_title); ?></span>
                    <img src="<?php echo $a($thumb); ?>" alt="" loading="lazy" decoding="async">
                </div>
                <div class="news-body">
                    <div class="news-date">
                        <?php echo $svgDate; ?>
                        <time datetime="<?php echo HTMLHelper::_('date', $row->publish_up, 'Y-m-d'); ?>">
                            <?php echo HTMLHelper::_('date', $row->publish_up, Text::_('DATE_FORMAT_LC3')); ?>
                        </time>
                    </div>
                    <h3><a href="<?php echo $a($route); ?>"><?php echo $e($row->title); ?></a></h3>
                    <a href="<?php echo $a($route); ?>" class="read">
                        <?php echo Text::_('TPL_LKIM3_READ_MORE'); ?><?php echo $svgArrow; ?>
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
