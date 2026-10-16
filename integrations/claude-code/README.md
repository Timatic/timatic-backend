Receives the events of the [Timatic plugin for Claude Code](https://github.com/tomasvanrijsse/timatic-claude):
a prompt sent and a question of Claude's answered.

Nothing to configure. Each user installs the plugin and logs in on their own machine through the
`claude-code` api client this package registers. That client accepts any loopback port on
`http://127.0.0.1/callback`, and its tokens stay valid as long as they are used at least once every
90 days.

Set `CLAUDE_CODE_PLUGIN_URL` to link the integrations overview to another copy of the plugin.
