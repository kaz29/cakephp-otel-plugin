<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Post> $posts
 */
?>
<div class="posts index content">
    <h3><?= __('投稿一覧') ?></h3>
    <?= $this->Html->link(__('新規投稿'), ['action' => 'add'], ['class' => 'button float-right']) ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><?= $this->Paginator->sort('id') ?></th>
                    <th><?= $this->Paginator->sort('title', __('タイトル')) ?></th>
                    <th><?= $this->Paginator->sort('created', __('作成日時')) ?></th>
                    <th><?= $this->Paginator->sort('modified', __('更新日時')) ?></th>
                    <th class="actions"><?= __('操作') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                <tr>
                    <td><?= $this->Number->format($post->id) ?></td>
                    <td><?= h($post->title) ?></td>
                    <td><?= h($post->created) ?></td>
                    <td><?= h($post->modified) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('詳細'), ['action' => 'view', $post->id]) ?>
                        <?= $this->Html->link(__('編集'), ['action' => 'edit', $post->id]) ?>
                        <?= $this->Form->postLink(__('削除'), ['action' => 'delete', $post->id], ['confirm' => __('本当に削除しますか？ # {0}', $post->id)]) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="paginator">
        <ul class="pagination">
            <?= $this->Paginator->first('<< ' . __('最初')) ?>
            <?= $this->Paginator->prev('< ' . __('前へ')) ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next(__('次へ') . ' >') ?>
            <?= $this->Paginator->last(__('最後') . ' >>') ?>
        </ul>
        <p><?= $this->Paginator->counter(__('{{page}} / {{pages}} ページ (全 {{count}} 件)')) ?></p>
    </div>
</div>
