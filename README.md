# AIICUT 火剪

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![Live](https://img.shields.io/badge/demo-aiicut.com-7C3AED)](https://www.aiicut.com/editor.html)

**在浏览器里完成视频剪辑，素材不出本机。** 无需安装、无需上传、无需等待。

在线体验：<https://www.aiicut.com/editor.html> · 官网：<https://www.aiicut.com/>

---

## 为什么是浏览器内剪辑

大多数在线剪辑工具要把视频传到服务器——这意味着等待上传、消耗流量、素材留在第三方机器上。

AIICUT 把裁剪、拼接、字幕、合成全部放在浏览器本地完成：**视频文件不会离开你的设备**。断网也能剪，剪敏感素材也不用担心。代价是导出不依赖服务端算力，耗时接近成片时长，需要保持页面在前台。

## 功能

| 能力 | 说明 |
| --- | --- |
| 导入 | 本地视频，单文件最大 500 MB，支持拖放 |
| 裁剪拼接 | 多片段裁剪、拆分、排序，拖放调整顺序 |
| 画幅适配 | 4 种指定画幅 + 原始比例，可选完整显示或居中裁切 |
| 变速 | 0.5× / 1× / 1.5× / 2×，可静音 |
| 字幕 | 手动定时字幕、SRT 导入导出、烧录进成片 |
| 截图 | 任意时间点导出 PNG |
| 导出 | 成片预览，浏览器实时导出 WebM / MP4（视浏览器支持） |

导出最长边不超过 1280px，建议使用桌面版 Chrome 或 Edge。

## 技术特点

- **零构建**：原生 HTML / CSS / JS，没有 Node、打包器或依赖安装
- **零后端依赖**：基础剪辑是纯静态页面，丢到任何静态托管即可运行
- **服务端仅用于 AI 生成**：`seedance.php` 按火山引擎方舟官方原生协议代理，API Key 只存在于服务端
- **安全实践**：CSRF token、`session.use_strict_mode`、HttpOnly + SameSite=Strict cookie、文件锁限流、每日额度上限、生成参数校验

## 本地运行

基础剪辑只需静态服务器：

```sh
php -S 127.0.0.1:8080 -t .
```

打开 <http://127.0.0.1:8080/>。AI 生成功能额外需要 PHP 7.2+ 与 cURL 扩展。

## 部署

将 HTML、CSS、JS、SVG、`sample.mp4` 与 `seedance.php` 部署到支持 PHP 的站点目录，默认首页设为 `index.html`。`README.md`、`DEPLOYMENT.md`、`LICENSE` 无需上传到网站目录。

AI 生成凭证通过环境变量或网站根目录外的私有配置文件提供，参考 [.env.example](.env.example)：

- `ARK_API_KEY`：火山引擎方舟 API Key
- `AIICUT_SEEDANCE_MODEL`：模型或推理接入点，默认 `doubao-seedance-2-0-260128`
- `AIICUT_CREATOR_CODE`：创作访问码，至少 12 字符

**请勿提交任何密钥、访问码、服务器登录信息或用户素材。** 详见 [部署说明](DEPLOYMENT.md)。

## 项目结构

```
index.html / style.css / app.js      官网
editor.html / editor.css / editor.js 剪辑工作台
editor-core.js                       时间处理与字幕转换
ai.html / ai.css / ai.js             AI 创作页面
seedance.php                         方舟原生协议代理及访问控制
sample.mp4                           剪辑测试用示例视频
```

## 当前状态

基础剪辑功能已实测可用（多片段拼接、变速静音、SRT 导入导出、字幕烧录均已验证）。

Seedance 视频生成的服务端代理已按官方协议实现，包括文生视频、首帧图生视频、异步任务查询与结果下载，但**尚未配置真实 API 密钥，因此真实生成流程还未验证**，线上该功能当前不可用。参考图仅支持公开 HTTPS URL，不提供服务器端素材上传。

## 参与贡献

欢迎提 issue 和 PR，尤其期待：

- 导出编码路径的进一步优化（目前依赖浏览器 `MediaRecorder`）
- 更多字幕样式与特效
- 项目自动保存（当前关闭，刷新即丢失）
- 更多语言界面

提交 PR 前请确认没有意外提交 `config.php`、`.env` 或任何真实素材。

## 许可证

[MIT](LICENSE) © 2026 AIICUT