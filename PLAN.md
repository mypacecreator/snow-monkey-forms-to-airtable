# 実装方針 (PLAN.md)

## 概要

Snow Monkey Forms のフォーム送信後に、Airtable Automation の Webhook トリガーへデータを転送する WordPress プラグインを実装する。

---

## ファイル構成

```
snow-monkey-forms-to-airtable/
├── snow-monkey-forms-to-airtable.php   # メインプラグインファイル
├── README.md                           # ユーザー向け説明
└── PLAN.md                             # 本ファイル（実装方針）
```

---

## 実装方針

### 1. フック

`snow_monkey_forms_after_send_mail` アクションフックを使用する。

```php
add_action( 'snow_monkey_forms_after_send_mail', 'smf_to_airtable_send', 10, 2 );
```

フックのコールバック引数は以下の通り。

| 引数 | 型 | 内容 |
|------|----|------|
| `$form_id` | string | フォームID (Snow Monkey Forms の投稿スラッグ or 投稿ID) |
| `$values`  | array  | フォームの送信データ（フィールド名 => 値 の連想配列） |

### 2. フォームID と Webhook URL のマッピング

**「Airtable 連携管理」専用のカスタム投稿タイプ（`airtable_mapping`）** を使用します。

#### 実装方式

- **投稿タイプ**: `airtable_mapping`
- **権限**: 非公開（`public => false, show_ui => true`）
- **カスタムフィールド**: 
  - `form_id`: フォーム識別子（Snow Monkey Forms の投稿スラッグまたは ID）
  - `webhook_url`: Airtable Automation の Webhook URL
- **フィールド実装**: WordPress 標準のメタフィールド（ACF Pro 不要）

#### マッピング取得ロジック

1. `snow_monkey_forms_after_send_mail` フックで `$form_id` を取得する。
2. 現在のサイト（`get_current_blog_id()` / `get_current_site()`）内の `airtable_mapping` 投稿を検索する。
3. メタフィールド `form_id` が一致する投稿から `webhook_url` を取得する。
4. URL が見つかった場合、次のステップ（データ送信）へ進む。

**サンプルコード（検索ロジック）:**

```php
$args = [
    'post_type'      => 'airtable_mapping',
    'posts_per_page' => 1,
    'meta_query'     => [
        [
            'key'   => 'form_id',
            'value' => $form_id,
            'compare' => '=',
        ],
    ],
];

$posts = new WP_Query( $args );
if ( $posts->have_posts() ) {
    $webhook_url = get_post_meta( $posts->posts[0]->ID, 'webhook_url', true );
}
```

#### マルチサイト対応

- 各サイトの `airtable_mapping` は独立して管理される。
- `WP_Query` は現在のサイトのデータベーステーブルを自動的に参照するため、マルチサイト対応は自動。

### 3. データ送信

- `$values` をそのまま `wp_json_encode()` で JSON 化する。
- フィールド名は動的（ループ処理）で、ハードコーディングしない。
- `wp_remote_post()` でリクエストを送信する。
- `blocking => false` を指定してノンブロッキング送信とし、WordPress のレスポンスへの影響を抑える。
- **注意**: `blocking => false` は待ち時間の削減であり、Airtable の 5 req/s レート制限を守るスロットリング・リトライではない。短時間の大量送信ではレート制限エラーが発生する可能性がある（将来的にはキュー/WP-Cron/リトライ対応を検討）。

```php
wp_remote_post( $webhook_url, [
    'headers'  => [ 'Content-Type' => 'application/json' ],
    'body'     => wp_json_encode( $values ),
    'blocking' => false,
] );
```

### 4. エラーハンドリング

- `$webhook_map` に該当フォームID が存在しない場合は処理をスキップする。
- Webhook URL が空文字・非文字列の場合もスキップする。
- `blocking => false` のため送信結果の確認は行わない（Airtable 側で確認する）。

### 5. 命名規則

- PHP 関数・フック名はプレフィックス `smf_to_airtable_` を使用する。
- クラスは使用せず、シンプルな関数ベースで実装する。

---

## Airtable 側の設定

1. Airtable の Automation 画面で「When webhook received」トリガーを作成する。
2. 発行された Webhook URL を WordPress 側のマッピングに設定する。
3. トリガー後のアクションとして「Create record」などを設定し、JSON のキーをフィールドにマッピングする。

---

## 実装詳細

### ファイル構成

メインプラグインファイル `snow-monkey-forms-to-airtable.php` に、すべての機能を実装：

```php
// プラグイン初期化
init()
├── register_mapping_post_type()  // CPT 登録
├── register_meta_fields()         // メタフィールド登録
└── add_action( 'snow_monkey_forms_after_send_mail' )  // フック登録

// フォーム送信時処理
send_to_airtable( $form_id, $values )
├── get_webhook_url_for_form( $form_id )  // マッピング検索
└── wp_remote_post()               // Webhook 送信
```

### 主要関数

#### `register_mapping_post_type()`
- カスタム投稿タイプ `airtable_mapping` を登録
- 権限: `public => false, show_ui => true`
- REST API対応: `show_in_rest => true`

#### `register_meta_fields()`
- メタフィールド `form_id` と `webhook_url` を登録
- 両フィールドは REST API から操作可能（`show_in_rest => true`）

#### `send_to_airtable( $form_id, $values )`
- `snow_monkey_forms_after_send_mail` アクションフックのコールバック
- `$form_id`: Snow Monkey Forms のフォーム ID
- `$values`: フォーム送信データ（連想配列）
- 処理:
  1. `get_webhook_url_for_form()` で Webhook URL を検索
  2. URL が見つかった場合、`$values` を JSON 化
  3. `wp_remote_post()` で Airtable へ非同期送信（`blocking => false`）

#### `get_webhook_url_for_form( $form_id )`
- `WP_Query` で `airtable_mapping` から `form_id` が一致する投稿を検索
- 見つかった場合、`webhook_url` メタフィールドを取得して返却
- マルチサイト対応: `WP_Query` は自動的に現在のサイトのテーブルを参照

### マルチサイト対応

- 各サイトの `airtable_mapping` は独立して管理される
- `WP_Query` は `get_current_blog_id()` / サイト固有のテーブルを自動参照
- サイト間でのマッピング設定の混在なし

### エラーハンドリング

- `$form_id` が空の場合、または `$values` が配列でない場合: 早期終了（`return`）
- Webhook URL が見つからない、または空文字列の場合: 処理をスキップ
- JSON エンコード失敗時: 処理をスキップ
- `blocking => false` のため、送信結果は確認しない（Airtable 側で確認）

---

## 将来的な拡張ポイント

- **マッピング設定方式の確定**（最優先課題）: 非開発者でも設定できる管理画面 UI（カスタム投稿タイプ + ACF 等）やテーマ外設定ファイル方式の採用を検討中
- **レート制限対策**: WP-Cron を使ったキューイング送信、指数バックオフによるリトライ、送信失敗の検知
- フィールドのキー名変換（例：日本語キー → Airtable フィールド名）
- 送信前の値バリデーション
- ログ出力（`WP_DEBUG_LOG` 連携）
