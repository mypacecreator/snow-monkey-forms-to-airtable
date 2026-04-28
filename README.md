# snow-monkey-forms-to-airtable

Snow Monkey Forms（WordPress フォームプラグイン）の送信データを、Airtable Automation の Webhook トリガーへ自動転送する WordPress プラグインです。

---

## 特徴

- Snow Monkey Forms の任意のフォームと Airtable の Webhook URL を紐付けられます。
- フォームの送信フィールドを汎用的にループ処理し、ハードコーディング不要で動作します。
- WordPress のレスポンスへの影響を抑えるため、ノンブロッキング送信（`blocking => false`）を採用しています。

---

## セットアップ

このプラグインは自動的に以下をセットアップします：

1. **カスタム投稿タイプの登録**: `airtable_mapping` CPT
2. **メタフィールドの登録**: `form_id`、`webhook_url`
3. **フック接続**: Snow Monkey Forms の送信フックに連携処理を登録

インストール後、すぐに管理画面の「Airtable マッピング」セクションからマッピング設定を開始できます。

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

1. リポジトリを `wp-content/mu-plugins/` 配下に設置します。

   ```
   wp-content/mu-plugins/snow-monkey-forms-to-airtable/
   ```

2. プラグインディレクトリにロードファイルを配置（WordPress が自動読み込み）。

   ```
   wp-content/mu-plugins/snow-monkey-forms-to-airtable.php
   ```

   このファイルの内容：

   ```php
   <?php
   // wp-content/mu-plugins/snow-monkey-forms-to-airtable.php
   require_once __DIR__ . '/snow-monkey-forms-to-airtable/snow-monkey-forms-to-airtable.php';
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

### マッピング管理方式

**「Airtable 連携管理」専用のカスタム投稿タイプ（`airtable_mapping`）** を使用します。

非開発者でも管理画面から設定でき、マルチサイト全体で動作します。WordPress 標準のカスタムフィールド（meta）を使用しており、**ACF Pro は不要**です。

#### 管理画面での設定手順

1. WordPress 管理画面にログインします。
2. サイドメニューに表示される **「Airtable マッピング」** をクリックします。
3. **「新規追加」** からマッピングを作成します。
4. 以下の情報を入力します：
   - **投稿タイトル**（管理用名）: 例「お問い合わせ → Airtable」
   - **フォーム ID**: Snow Monkey Forms のフォームスラッグまたは投稿 ID
   - **Webhook URL**: Airtable Automation から取得した Webhook URL

#### フォーム ID の確認方法

1. Snow Monkey Forms の投稿一覧画面を開きます。
2. 対象フォームの **スラッグ** を確認します。
3. スラッグが設定されていない場合は、投稿 ID（数値）を使用できます。

#### マッピング管理画面の仕様

- **権限**: 非公開（WordPress 管理画面のみで表示・管理可能）
- **フィールド構成**:
  - タイトル（管理用）
  - `form_id`: フォーム識別子
  - `webhook_url`: Airtable Webhook URL

### マルチサイト対応

本プラグインはマルチサイト構成に対応しています。

- 各サイト（ブログ）は独立した `airtable_mapping` 投稿を管理します。
- フォーム送信時、プラグインは**現在のサイト内**の `airtable_mapping` から対応する Webhook URL を自動検索します。
- サイト間でのマッピング設定の混在や干渉はありません。

### インストール場所（mu-plugins 推奨）

マルチサイト環境では、プラグインを **mu-plugins**（Must Use Plugins）ディレクトリに配置することを推奨します。

```
wp-content/mu-plugins/snow-monkey-forms-to-airtable/
```

**利点**:
- サイト個別の有効化・無効化が不要（全サイトで自動有効）
- マルチサイト全体で一括管理できる

---

## マッピング管理画面での実装詳細

### カスタム投稿タイプ「airtable_mapping」

プラグインは自動的に以下のカスタム投稿タイプを登録します：

| 項目 | 値 |
|------|-----|
| **投稿タイプ ID** | `airtable_mapping` |
| **管理画面表示名** | 「Airtable マッピング」 |
| **権限** | 非公開（WordPress 管理画面のみで表示） |
| **サポート機能** | タイトルのみ |
| **アイコン** | クラウドアイコン（dashicons-cloud） |
| **REST API対応** | あり |

### カスタムメタフィールド

投稿データに以下のメタフィールドを自動登録します：

| フィールド | 説明 | 型 |
|-----------|------|-----|
| **form_id** | Snow Monkey Forms のフォーム識別子（スラッグまたはID） | 文字列 |
| **webhook_url** | Airtable Automation の Webhook URL | 文字列 |

### 管理画面での編集方法

WordPress の標準メタボックスか REST API を使用して編集できます。

**REST API での例:**

```bash
curl -X POST https://example.com/wp-json/wp/v2/airtable_mapping \
  -H "Content-Type: application/json" \
  -d '{
    "title": "お問い合わせ → Airtable",
    "meta": {
      "form_id": "contact",
      "webhook_url": "https://hooks.airtable.com/workflows/v1/genericWebhook/xxxxx"
    }
  }' \
  --user admin
```

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

## トラブルシューティング

### マッピングが認識されない場合

1. 「Airtable マッピング」から投稿を作成したか確認します。
2. メタフィールド `form_id` に正しいフォーム ID が設定されているか確認します。
   - Snow Monkey Forms の投稿スラッグまたは投稿 ID を使用してください。
3. メタフィールド `webhook_url` に正しい URL が設定されているか確認します。
   - `https://hooks.airtable.com/workflows/v1/genericWebhook/` で始まる URL か確認してください。

### データが Airtable に届かない場合

1. `blocking => false` のため、WordPress のレスポンスは即座に返却されます。
   - Airtable 側でレコードが作成されているか確認してください。
2. Airtable の Automation トリガーが正しく設定されているか確認します。
3. WordPress のデバッグログを有効化して、エラーを確認します。

   ```php
   // wp-config.php に以下を追加
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   define( 'WP_DEBUG_DISPLAY', false );
   ```

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
