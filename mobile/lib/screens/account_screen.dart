import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../config.dart';
import '../services/api_client.dart';
import '../services/auth_state.dart';
import '../theme.dart';
import '../widgets/legal_links.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({super.key});

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  bool _busy = false;
  String? _error;

  Future<void> _confirmDelete() async {
    final password = await showDialog<String>(
      context: context,
      builder: (ctx) => const _DeleteAccountDialog(),
    );

    if (password == null || password.isEmpty || !mounted) return;

    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<AuthState>().deleteAccount(password);
      if (mounted) {
        Navigator.of(context).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Account deleted.')),
        );
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthState>().user;

    return Scaffold(
      appBar: AppBar(title: const Text('Account')),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Text(
            user?.email ?? '',
            style: const TextStyle(color: LiveMixTheme.mist, fontSize: 16, fontWeight: FontWeight.w600),
          ),
          if (user?.name != null && user!.name.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(user.name, style: const TextStyle(color: LiveMixTheme.mute)),
          ],
          const SizedBox(height: 24),
          const LegalLinks(),
          const SizedBox(height: 8),
          TextButton(
            onPressed: () => openExternalUrl('mailto:${AppConfig.supportEmail}'),
            child: const Text(
              AppConfig.supportEmail,
              style: TextStyle(color: LiveMixTheme.mute, decoration: TextDecoration.underline),
            ),
          ),
          const SizedBox(height: 32),
          const Text(
            'Delete account',
            style: TextStyle(color: LiveMixTheme.bad, fontWeight: FontWeight.w700, fontSize: 16),
          ),
          const SizedBox(height: 8),
          const Text(
            'Removes your login from Sound Mix Live. Required for Google Play and App Store account-deletion policy.',
            style: TextStyle(color: LiveMixTheme.mute, height: 1.4),
          ),
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(_error!, style: const TextStyle(color: LiveMixTheme.bad)),
          ],
          const SizedBox(height: 16),
          OutlinedButton(
            onPressed: _busy ? null : _confirmDelete,
            style: OutlinedButton.styleFrom(
              foregroundColor: LiveMixTheme.bad,
              side: const BorderSide(color: LiveMixTheme.bad),
            ),
            child: Text(_busy ? 'Deleting…' : 'Delete my account'),
          ),
        ],
      ),
    );
  }
}

class _DeleteAccountDialog extends StatefulWidget {
  const _DeleteAccountDialog();

  @override
  State<_DeleteAccountDialog> createState() => _DeleteAccountDialogState();
}

class _DeleteAccountDialogState extends State<_DeleteAccountDialog> {
  final _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      backgroundColor: LiveMixTheme.panel,
      title: const Text('Delete account?'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'This permanently deletes your Sound Mix Live login and API tokens. '
            'Channel content may remain for other organizers.',
            style: TextStyle(color: LiveMixTheme.mute, height: 1.4),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _controller,
            obscureText: true,
            autofillHints: const [AutofillHints.password],
            decoration: const InputDecoration(labelText: 'Password'),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        TextButton(
          onPressed: () => Navigator.pop(context, _controller.text),
          child: const Text('Delete', style: TextStyle(color: LiveMixTheme.bad)),
        ),
      ],
    );
  }
}
