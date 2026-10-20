# ⚡ 高併發搶票與秒殺系統 (Ticket Flash-Sale)

基於 **PHP 8.3 + Redis 訊息佇列 + 獨立 CLI Background Worker + MySQL** 的高併發搶票解決方案，解決瞬間流量衝垮資料庫與超賣問題。

## 🌟 核心特色
- **Redis Lua 原子操作**：扣減庫存與防重複搶票一氣呵成，杜絕競態超賣。
- **訊息佇列削峰**：Web 端只負責收單並推入 Queue，立即回應釋放連線。
- **獨立 CLI Worker**：背景程式以平穩速率從 Redis 佇列消化訂單，保護 MySQL 不崩潰。
- **樂觀鎖與唯一索引**：資料庫交易保護，達成最終數據一致性。

---

## 🚀 快速啟動

### 1. 安裝套件
```bash
composer install