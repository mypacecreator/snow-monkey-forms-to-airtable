# snow-monkey-forms-to-airtable

Snow Monkey Forms（WordPress フォームプラグイン）の送信データを、Airtable Automation の Webhook トリガーへ自動転送する WordPress プラグインです。

---

## 特徴

- Snow Monkey Forms の任意のフォームと Airtable の Webhook URL を紐付けられます。
- フォームの送信フィールドを汎用的にループ処理し、ハードコーディング不要で動作します。
- `snow_monkey_forms/administrator_mailer/after_send` と `snow_monkey_forms/administrator_mailer/is_sended` の2つのフックに対応しており、Snow Monkey Forms のバージョン差異に強い二重フック方式を採用しています。
- 通常時はノンブロッキング送信（`blocking => false`）で WordPress のレスポンスへの影響を最小化します。`WP_DEBUG_LOG` が有効な場合はブロッキング送信に切り替わり、Airtable からのレスポンスを取得してデバッグログへ記録します。

---

## 必要環境

| 項目 | バージョン |
|------|-----------|
| WordPress | 6.0 以上推奨 |
| [Snow Monkey Forms](https://github.com/inc2734/snow-monkey-forms) | 最新版推奨 |
| PHP | 7.4 以上 |

---

## インストール

### 単一サイト環境

1. このリポジトリを ZIP でダウンロードするか、`git clone` でサイトの `wp-content/plugins/` 配下に設置します。

   ```
   wp-content/plugins/snow-monkey-forms-to-airtable/
   ```

2. WordPress 管理画面の **プラグイン** メニューから「Snow Monkey Forms to Airtable」を有効化します。

### マルチサイト環境（推奨）

マルチサイト環境では、`mu-plugins`（Must Use Plugins）ディレクトリに配置することで、すべてのサイトで自動有効化されます。

> **注意:** WordPress の mu-plugins はサブディレクトリ内の PHP ファイルを自動読み込みしません。  
> 参考: [Must Use Plugins – WordPress サポート](https://ja.wordpress.org/support/article/must-use-plugins/)

以下のいずれかの方法で設置してください。

#### 方法 A: PHP ファイル 1 本を直接 mu-plugins 直下に置く

リポジトリからメインの PHP ファイルだけを `wp-content/mu-plugins/` 直下にコピーします。

```
wp-content/mu-plugins/snow-monkey-forms-to-airtable.php
```

プラグインがアップデートされた場合はファイルを手動で差し替える必要があります。

#### 方法 B: サブディレクトリ + プロキシローダーファイル（推奨）

リポジトリをサブディレクトリに `git clone` などで設置し、mu-plugins 直下にローダーファイルを 1 本置く方法です。アップデートを `git pull` で管理できます。

1. リポジトリをサブディレクトリに設置します。

   ```
   wp-content/mu-plugins/snow-monkey-forms-to-airtable/
   ```

2. mu-plugins 直下に `load.php` という名前のローダーファイルを作成します。

   > **補足:** ローダーファイルの名前はプラグイン名と同じにする必要はありません。`load.php` のような汎用的な名前にしておくと、複数のプラグインを同じファイルで読み込む場合にも対応しやすくなります。

   ```
   wp-content/mu-plugins/load.php
   ```

   ファイルの内容：

   ```php
   <?php
   /**
    * Plugin Name: MU Plugins Loader
    * Description: mu-plugins ディレクトリのサブディレクトリに置いたプラグインを読み込むローダー。
    */

   require_once __DIR__ . '/snow-monkey-forms-to-airtable/snow-monkey-forms-to-airtable.php';
   ```

   > **補足:** `Plugin Name` ヘッダーを記述しておくと、WordPress 管理画面の **「インストール済みプラグイン」→「MU プラグイン」** タブにローダーファイルが一覧表示され、稼働状況を確認しやすくなります。

#### 複数の mu-plugins を使用する場合

サブディレクトリ方式で複数の mu-plugins を運用する場合は、同じ `load.php` に `require_once` を追記するだけで対応できます。

**ディレクトリ構成例:**

```
wp-content/mu-plugins/
├── load.php                          ← ローダーファイル（1本）
├── snow-monkey-forms-to-airtable/    ← 本プラグイン
│   └── snow-monkey-forms-to-airtable.php
└── another-plugin/                   ← 別の mu-plugin
    └── another-plugin.php
```

**`load.php` の内容例:**

```php
<?php
/**
 * Plugin Name: MU Plugins Loader
 * Description: mu-plugins ディレクトリのサブディレクトリに置いたプラグインを読み込むローダー。
 */

require_once __DIR__ . '/snow-monkey-forms-to-airtable/snow-monkey-forms-to-airtable.php';
require_once __DIR__ . '/another-plugin/another-plugin.php';
```

---

## Airtable 側の設定

1. Airtable のベース（Base）を開き、**Automations** タブへ移動します。
2. **+ Add automation** → トリガーに **「When webhook received」** を選択します。
3. 表示される **Webhook URL** をコピーしておきます。
4. アクションとして **「Create record」** などを追加し、受け取った JSON のキーと Airtable フィールドをマッピングします。

詳細は [Airtable 公式サポートページ](https://support.airtable.com/docs/when-webhook-received-trigger) を参照してください。

---

## WordPress 側の設定

プラグインは有効化すると自動的に以下をセットアップします：

- カスタム投稿タイプ `airtable_mapping` の登録
- カスタムメタフィールド `smfa_form_id`・`smfa_webhook_url` の登録
- Snow Monkey Forms の送信フックへの連携処理登録

### マッピングの作成手順

1. WordPress 管理画面にログインします。
2. サイドメニューの **「Airtable マッピング」** をクリックします。
3. **「新規追加」** からマッピングを作成します。
4. 以下の情報を入力します：

   | 項目 | 内容 |
   |------|------|
   | **投稿タイトル**（管理用名） | 例：「お問い合わせ → Airtable」 |
   | **フォーム ID** | Snow Monkey Forms のフォーム**投稿 ID**（数値）を推奨 |
   | **Webhook URL** | Airtable Automation から取得した Webhook URL |

5. 「公開」または「更新」を押して保存します。

### フォーム ID の確認方法

Snow Monkey Forms の管理画面（投稿一覧）を開き、対象フォームの投稿 ID（URL に表示される `post=XXXXX` の数値）を確認してください。

プラグインはフォーム送信時に Snow Monkey Forms の設定オブジェクト（`$setting`）から投稿 ID を自動取得するため、フォーム内に隠しフィールドを追加する必要はありません。

### マルチサイト対応

- 各サイト（ブログ）は独立した `airtable_mapping` 投稿を管理します。
- フォーム送信時、プラグインは**現在のサイト内**の `airtable_mapping` から対応する Webhook URL を自動検索します。
- サイト間でのマッピング設定の混在・干渉はありません。

---

## 動作の流れ

```
ユーザーがフォームを送信
        ↓
Snow Monkey Forms が管理者メール送信を実行
        ↓
snow_monkey_forms/administrator_mailer/after_send（または is_sended）フックが発火
        ↓
本プラグインが $setting オブジェクトからフォーム識別子を解決
（解決できない場合のみ $responser を補助的に参照）
        ↓
airtable_mapping からフォーム ID に対応する Webhook URL を検索
        ↓
フォーム送信データを JSON に変換して Airtable へ POST 送信
（通常: 非同期送信 / WP_DEBUG_LOG 有効時: 同期送信してレスポンスをログ記録）
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

## フィールド名について

Webhook ペイロードの JSON キーは Snow Monkey Forms のフィールド名がそのまま使用されます（変換なし）。Airtable は Unicode（日本語を含む）をフィールド名および Webhook ペイロードの JSON キーとして UTF-8 で受け付けるため、日本語フィールド名でも技術的には問題なく動作します。

Airtable Automation の設定時は、テスト用 Webhook を送信してサンプルペイロードを読み込ませると、JSON キーが変数として選択できるようになります。その後、アクション（「Create record」など）で各 JSON キーを Airtable のテーブルフィールドへ手動でマッピングします。この対応付けはフィールド名が一致していなくても可能ですが、名前を揃えておくと設定時の見通しが良くなります。

**推奨方針:**

- Airtable テーブルのフィールド名が**日本語**の場合 → Snow Monkey Forms のフィールド名も日本語にすると直感的に対応付けできます。
- Airtable テーブルのフィールド名が**英語（ASCII）**の場合 → Snow Monkey Forms のフィールド名も英語にしておくとマッピング時の混乱を避けられます。

---

## デバッグログ

`WP_DEBUG_LOG` が有効な場合、プラグインは送信モードを切り替えてレスポンスをログに記録します。

**wp-config.php:**

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

有効時の動作変化:

| 項目 | 通常時 | WP_DEBUG_LOG 有効時 |
|------|--------|---------------------|
| `blocking` | `false`（ノンブロッキング） | `true`（レスポンス待ち） |
| `timeout` | 1 秒 | 10 秒 |
| ログ出力 | なし | 常時（成功・失敗ともに） |

**ログ出力例:**

```
[SMF to Airtable] form_id=42 success=true status=200
[SMF to Airtable] form_id=42 success=false error=cURL error 28: Operation timed out
```

---

## 実装詳細

### フック構成

プラグインは2つのフックを登録し、一方が処理を完了した場合はもう一方をスキップする（`$already_processed` による重複防止）仕組みになっています。

| フック | 種別 | 用途 |
|--------|------|------|
| `snow_monkey_forms/administrator_mailer/after_send` | アクション | メイン処理 |
| `snow_monkey_forms/administrator_mailer/is_sended` | フィルター | フォールバック（after_send が発火しない環境向け） |

### カスタム投稿タイプ `airtable_mapping`

| 項目 | 値 |
|------|----|
| 投稿タイプ ID | `airtable_mapping` |
| 管理画面表示名 | 「Airtable マッピング」 |
| 公開設定 | 非公開（管理画面のみで表示） |
| サポート機能 | タイトルのみ |
| アイコン | `dashicons-cloud` |
| REST API 対応 | あり（`show_in_rest => true`） |

### カスタムメタフィールド

| フィールド | 説明 | 型 |
|-----------|------|-----|
| `smfa_form_id` | Snow Monkey Forms のフォーム投稿 ID | 文字列 |
| `smfa_webhook_url` | Airtable Automation の Webhook URL | 文字列 |

## トラブルシューティング

### マッピングが認識されない場合

1. 「Airtable マッピング」から投稿を作成し、「公開」済みであることを確認します。
2. メタフィールド `smfa_form_id` に Snow Monkey Forms の**投稿 ID**（数値）が入力されているか確認します。
3. メタフィールド `smfa_webhook_url` に正しい URL が設定されているか確認します。

### データが Airtable に届かない場合

通常時は `blocking => false` のため、WordPress のレスポンスは即座に返却されます。以下の手順で原因を切り分けてください。

1. `WP_DEBUG_LOG` を有効化し、`wp-content/debug.log` に出力されるエラーを確認します。
2. Airtable の Automation 画面で Run history を確認し、Webhook が受信されているか確認します。
3. `WP_DEBUG_LOG` を有効化した状態でフォームを送信し、ログに `success=false` と出力される場合は `error=` の内容で原因を特定します。

### レート制限について

Airtable の Webhook は毎秒 5 リクエストの制限があります。現在の実装ではスロットリングやリトライは行いません。

> **注意:** 短時間に大量のフォーム送信が発生した場合、Airtable 側でレート制限エラーが発生する可能性があります。
