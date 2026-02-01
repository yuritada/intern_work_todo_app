# ==========================================
# 設定: ここでイメージ名とコンテナ名を管理します
# ==========================================
export IMAGE_NAME := todo-app-image
export CONTAINER_NAME := todo-app-container

# Docker Composeファイルのパス
COMPOSE_FILE := docker/docker-compose.yml

# ==========================================
# コマンド定義
# ==========================================
.PHONY: up down clean help logs db-shell db-show db-users db-select

# デフォルトターゲット
help:
	@echo "使用可能なコマンド:"
	@echo "  make up      - アプリケーションを立ち上げます (ビルド含む)"
	@echo "  make down    - コンテナを停止・削除します"
	@echo "  make clean   - コンテナ、ネットワーク、および作成したイメージ($(IMAGE_NAME))を削除します"
	@echo "  make logs    - コンテナのログを表示します"
	@echo "  make db-shell - DBコンテナに接続してMySQLシェルを開きます"
	@echo "  make db-show  - DB内のテーブル一覧を表示します
	@echo "  make db-users - usersテーブルの内容を表示します"
	@echo "  make db-select table=<table_name> - 指定したテーブルの内容を表示します"

# 立ち上げ (ビルドしてから起動)
up:
	@echo "Starting up $(CONTAINER_NAME) with image $(IMAGE_NAME)..."
	docker-compose -f $(COMPOSE_FILE) up -d --build

# 停止
down:
	@echo "Stopping $(CONTAINER_NAME)..."
	docker-compose -f $(COMPOSE_FILE) down

# クリーンアップ (イメージも削除)
clean:
	@echo "Cleaning up $(CONTAINER_NAME) and image $(IMAGE_NAME)..."
	# --rmi local: このcomposeファイルでビルドされたイメージを削除
	# -v: ボリュームも削除（DBデータなども消したい場合）
	docker-compose -f $(COMPOSE_FILE) down --rmi local -v --remove-orphans
	@echo "Done."

# ログ表示
logs:
	@echo "Showing logs for $(CONTAINER_NAME)..."
	docker-compose -f $(COMPOSE_FILE) logs -f
# ==========================================
# DBデータ確認用コマンド
# ==========================================

# DB接続（ターミナルで対話操作）
db-shell:
	docker-compose -f $(COMPOSE_FILE) exec db mysql -u root -p

# DBのデータを簡易表示（テーブル一覧を表示する例）
db-show:
	docker-compose -f $(COMPOSE_FILE) exec db mysql -u root -proot fuelphp -e "SHOW TABLES;"

# 特定のテーブル（例: users）の中身を表示
db-users:
	docker-compose -f $(COMPOSE_FILE) exec db mysql -u root -proot fuelphp -e "SELECT * FROM users;"

# 実行時にテーブル名を指定して表示 (例: make db-select table=tasks)
db-select:
	docker-compose -f $(COMPOSE_FILE) exec db mysql -u root -proot fuelphp -e "SELECT * FROM $(table);"