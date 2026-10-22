# AIICUT 火剪

AI 视频剪辑官网与浏览器剪辑工作台。

- 官网：https://www.aiicut.com/
- 剪辑工作台：https://www.aiicut.com/editor.html
- AI 视频创作：https://www.aiicut.com/ai.html

## 功能

- 本地视频导入、裁剪、分段与片段排序
- 多画幅适配、居中裁切、倍速与静音
- 定时字幕编辑、SRT 导入导出、PNG 截图
- 成片预览及浏览器实时视频导出
- Seedance 2.0 视频生成页面与服务端代理（待配置凭证、验证真实生成）

基础剪辑在浏览器内处理，不上传视频素材。项目暂不自动保存；导出时保持页面在前台。建议桌面 Chrome 或 Edge。导出格式取决于浏览器支持，通常为 WebM，最长边不超过 1280px。

## 本地运行

需要 PHP 7.2 或以上版本；AI 接口还需要 PHP cURL 扩展。

```sh
php -S 127.0.0.1:8080 -t .
```

打开 http://127.0.0.1:8080/ 。仅检查静态页面也可用任意静态服务器，但 AI 接口需要 PHP。

## 部署

将应用 HTML、CSS、JS、SVG、示例视频和 `seedance.php` 部署到支持 PHP 的网站目录，默认首页设为 `index.html`。无需 Node 构建步骤。README 和部署说明不需要上传到网站目录。

Seedance 使用服务器环境变量 `ARK_API_KEY`、`AIICUT_SEEDANCE_MODEL`、`AIICUT_CREATOR_CODE`，也支持根目录外的私有配置文件。不要将密钥、访问码、服务器登录信息或素材上传到 GitHub。详见 [部署说明](DEPLOYMENT.md)。

## 文件

- `index.html` / `style.css` / `app.js`：官网
- `editor.html` / `editor.css` / `editor.js`：剪辑工作台
- `editor-core.js`：时间处理与字幕转换
- `ai.html` / `ai.css` / `ai.js`：AI 创作页面
- `seedance.php`：方舟原生协议代理及访问控制
- `sample.mp4`：本地生成的剪辑测试示例

备案号：粤ICP备16038098号-4。
