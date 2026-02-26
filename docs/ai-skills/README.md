# AI Skills Sync

このディレクトリは、本リポジトリ用の追加スキル（自社ルール）を管理するためのものです。
公式スキルは別途インストールし、ここで管理するスキルを上書き・追記として配布します。

## 運用手順
1. 参照元の更新
   - `docs/common/common.md`
   - `docs/ai-skills/skills/coding-rules.md`
   - `docs/ai-skills/skills/design-rules.md`
   - `docs/ai-skills/skills/phpunit.md`
2. スキルの説明が必要なら `docs/ai-skills/local-project-rules/SKILL.md` を更新
3. 公式スキルの更新＋自社ルールの配布
   - `npm run skills:update`
4. 自社ルールだけを配布
   - `npm run skills:sync`

## 配布先
- `.codex/skills/`
- `.cursor/skills/`
- `.github/skills/`
- `.claude/skills/`
- `.agent/skills/`（Antigravity）
