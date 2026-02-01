<?php
/**
 * Quest-Logic ダッシュボードコントローラー (Controller_Dashboard)
 *
 * 【解説: Controller_Base の継承】
 * このコントローラーは認証が必要なため、Controller_Base を継承します。
 * これにより before() で自動的に認証チェックが行われ、
 * 未ログインユーザーはログイン画面へリダイレクトされます。
 *
 * Phase 3 で詳細な実装を行います。
 */
class Controller_Dashboard extends Controller_Base
{
    /**
     * ダッシュボード（トップページ）
     *
     * 現在遭遇中のボス（締切の近い親タスク）や
     * プロジェクトの進行状況を表示します。
     */
    public function action_index()
    {
        // Phase 3 でプロジェクト・タスク一覧を取得する処理を実装
        $data = array(
            'projects' => array(), // TODO: 実装
        );

        $this->template->title = 'Dashboard';
        $this->template->content = \View::forge('dashboard/index', $data);
    }
}
