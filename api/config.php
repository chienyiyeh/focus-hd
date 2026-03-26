<?php
/**
 * API 設定檔統一轉接到專案根目錄 config.php
 * 避免 api/config.php 與 config.php 內容分歧造成部署後錯誤。
 */
require_once __DIR__ . '/../config.php';
