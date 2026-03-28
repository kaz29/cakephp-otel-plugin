<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Post $post
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('操作') ?></h4>
            <?= $this->Html->link(__('投稿一覧'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="posts form content">
            <?= $this->Form->create($post) ?>
            <fieldset>
                <legend><?= __('新規投稿') ?></legend>
                <?= $this->Form->control('title', ['label' => __('タイトル')]) ?>
                <?= $this->Form->control('body', ['label' => __('本文'), 'type' => 'textarea']) ?>
            </fieldset>
            <?= $this->Form->button(__('投稿する')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
