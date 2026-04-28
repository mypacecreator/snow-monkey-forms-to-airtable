# snow-monkey-forms-to-airtable

Snow Monkey Forms（WordPress フォームプラグイン）の送信データを、Airtable Automation の Webhook トリガーへ自動転送する WordPress プラグインです。

---

## 特徴

- Snow Monkey Forms の任意のフォームと Airtable の Webhook URL を紐付けられます。
- フォームの送信フィールドを汎用的にループ処理し、ハードコーディング不要で動作します。
- WordPress のレスポンスへの影響を抑えるため、ノンブロッキング送信（`blocking => false`）を採用しています。

---

## 必要環境

| 項目 | バージョン |
|------|-----------|
| WordPress | 6.0 以上推奨 |
| [Snow Monkey Forms](https://github.com/inc2734/snow-monkey-forms) | 最新版推奨 |
| PHP | 7.4 以上 |

---

## インストール

1. このリポジトリを ZIP でダウンロードするか、`git clone` でサイトの `wp-content/plugins/` 配下に設置します。

   ```
   wp-content/plugins/snow-monkey-forms-to-airtable/
   ```

2. WordPress 管理画面の **プラグイン** メニューから「Snow Monkey Forms to Airtable」を有効化します。

---

## Airtable 側の設定

1. Airtable のベース（Base）を開き、**Automations** タブへ移動します。
2. **+ Add automation** → トリガーに **「When webhook received」** を選択します。
3. 表示される **Webhook URL** をコピーしておきます。
4. アクションとして **「Create record」** などを追加し、受け取った JSON のキーと Airtable フィールドをマッピングします。

詳細は [Airtable 公式サポートページ](https://support.airtable.com/docs/when-webhook-received-trigger) を参照してください。

---

## WordPress 側の設定

> **⚠️ 設定方法は検討中です**
>
> フォーム ID と Webhook URL のマッピング設定をどこに・どのように定義するかは、**現在も運用しやすい方法を模索中**です。
> 以下は検討中の候補です。決定次第、このドキュメントを更新します。
>
> - **管理画面 UI（カスタム投稿タイプ + ACF）**: 「Airtable 連携管理」専用の投稿タイプを作成し、非開発者でも管理画面から設定できる形
> - **テーマ外の設定ファイル**: サーバー上のテーマディレクトリ外に配置した PHP 設定ファイルで管理
> - **`functions.php` / プラグインファイルへの直接記述**（暫定案）: 開発者向けの最もシンプルな方法

現時点での暫定的な実装例（フィルターフック経由）：

```php
add_filter( 'smf_to_airtable_webhook_map', function( $map ) {
    // キー: Snow Monkey Forms のフォーム投稿スラッグ（または投稿ID）
    // 値  : Airtable Automation の Webhook URL
    $map['contact']  = 'https://hooks.airtable.com/workflows/v1/genericWebhook/xxxxxxxx';
    $map['estimate'] = 'https://hooks.airtable.com/workflows/v1/genericWebhook/yyyyyyyy';
    return $map;
} );
```

> **フォーム ID の確認方法**
> Snow Monkey Forms の投稿一覧画面でフォームの投稿スラッグを確認してください。
> スラッグが設定されていない場合は投稿 ID（数値）をキーとして使用できます。

---

## 動作の流れ

```
ユーザーがフォームを送信
        ↓
Snow Monkey Forms がメール送信処理を実行
        ↓
snow_monkey_forms_after_send_mail フックが発火
        ↓
本プラグインがフォーム ID を確認し、対応する Webhook URL を取得
        ↓
フォーム送信データを JSON に変換して Airtable へ POST 送信（非同期）
        ↓
Airtable Automation がレコードを作成
```

---

## 送信データの形式

フォームの `$values`（フィールド名 => 値 の連想配列）をそのまま JSON に変換して送信します。

**送信例:**

```json
{
  "お名前": "山田 太郎",
  "メールアドレス": "taro@example.com",
  "お問い合わせ内容": "製品についてお聞きしたいことがあります。"
}
```

Airtable 側では、JSON のキー名（フィールド名）と Airtable のフィールド名を Automation のアクション設定でマッピングしてください。

---

## レート制限への対応

Airtable の Webhook は毎秒 5 リクエストの制限があります。

現在の実装では `wp_remote_post()` に `blocking => false` を指定することで、**WordPress のレスポンスへの影響を最小限に抑えています**。ただし、これはノンブロッキング化（待ち時間の削減）であり、Airtable 側の 5 req/s 制限を守るスロットリングやリトライではありません。

> **⚠️ 注意**
> 短時間に大量のフォーム送信が発生した場合、Airtable 側でレート制限エラーが発生する可能性があります。
> 現時点では送信失敗の検知・リトライは行いません（`blocking => false` のため）。

将来的な対応として、以下を検討しています：
- WP-Cron を使ったキューイング送信
- 指数バックオフによるリトライ
- 送信失敗の `WP_DEBUG_LOG` への記録

---

## ライセンス

MIT
