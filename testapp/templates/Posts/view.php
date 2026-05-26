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
            <?= $this->Html->link(__('編集'), ['action' => 'edit', $post->id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(__('削除'), ['action' => 'delete', $post->id], ['confirm' => __('本当に削除しますか？'), 'class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="posts view content">
            <h3><?= h($post->title) ?></h3>
            <div class="text">
                <?= $this->Text->autoParagraph(h($post->body)); ?>
            </div>
            <table>
                <tr>
                    <th><?= __('作成日時') ?></th>
                    <td><?= h($post->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('更新日時') ?></th>
                    <td><?= h($post->modified) ?></td>
                </tr>
            </table>
        </div>

        <div class="related">
            <h4><?= __('コメント') ?></h4>
            <?php if (!empty($post->comments)): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th><?= __('投稿者') ?></th>
                            <th><?= __('コメント') ?></th>
                            <th><?= __('日時') ?></th>
                            <th class="actions"><?= __('操作') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($post->comments as $comment): ?>
                        <tr>
                            <td><?= h($comment->author) ?></td>
                            <td><?= h($comment->body) ?></td>
                            <td><?= h($comment->created) ?></td>
                            <td class="actions">
                                <?= $this->Form->postLink(__('削除'), ['controller' => 'Comments', 'action' => 'delete', $comment->id], ['confirm' => __('このコメントを削除しますか？')]) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p><?= __('コメントはまだありません。') ?></p>
            <?php endif; ?>

            <div class="comments form content">
                <h4><?= __('コメントを追加') ?></h4>
                <?= $this->Form->create(null, ['url' => ['controller' => 'Comments', 'action' => 'add', $post->id]]) ?>
                <fieldset>
                    <?= $this->Form->control('author', ['label' => __('名前')]) ?>
                    <?= $this->Form->control('body', ['label' => __('コメント'), 'type' => 'textarea']) ?>
                </fieldset>
                <?= $this->Form->button(__('送信')) ?>
                <?= $this->Form->end() ?>
            </div>
        </div>
    </div>
</div>
