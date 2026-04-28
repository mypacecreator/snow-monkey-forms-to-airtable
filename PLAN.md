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

ハードコーディングを避けるため、設定はフィルターフックで外部から注入できる形にする。

```php
$webhook_map = apply_filters( 'smf_to_airtable_webhook_map', [] );
```

利用者は `functions.php` などに以下のように記述して設定する。

```php
add_filter( 'smf_to_airtable_webhook_map', function( $map ) {
    $map['contact']  = 'https://hooks.airtable.com/workflows/v1/genericWebhook/xxxxx';
    $map['estimate'] = 'https://hooks.airtable.com/workflows/v1/genericWebhook/yyyyy';
    return $map;
} );
```

### 3. データ送信

- `$values` をそのまま `wp_json_encode()` で JSON 化する。
- フィールド名は動的（ループ処理）で、ハードコーディングしない。
- `wp_remote_post()` でリクエストを送信する。
- Airtable のレート制限（毎秒 5 リクエスト）を考慮し、`blocking => false` を指定して非同期送信とする。

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

## 将来的な拡張ポイント

- フィールドのキー名変換（例：日本語キー → Airtable フィールド名）
- 送信前の値バリデーション
- ログ出力（`WP_DEBUG_LOG` 連携）
- 管理画面 UI でのマッピング設定
