import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../brand.dart';
import '../config.dart';
import '../services/api_client.dart';
import '../services/auth_state.dart';
import '../theme.dart';
import '../widgets/brand_mark.dart';
import '../widgets/legal_links.dart';
import '../widgets/network_banner.dart';
import 'creator_home_screen.dart';
import 'login_screen.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  bool _busy = false;
  String? _error;

  Future<void> _pickAvatar() async {
    final auth = context.read<AuthState>();
    if (!auth.isLoggedIn) return;

    final picker = ImagePicker();
    final file = await picker.pickImage(
      source: ImageSource.gallery,
      maxWidth: 1024,
      maxHeight: 1024,
      imageQuality: 88,
      requestFullMetadata: false,
    );
    if (file == null || !mounted) return;

    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await auth.uploadAvatar(file.path);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Profile photo updated.')),
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

  Future<void> _editName() async {
    final auth = context.read<AuthState>();
    final user = auth.user;
    if (user == null) return;

    final controller = TextEditingController(text: user.name);
    final next = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: LiveMixTheme.panel,
        title: const Text('Display name'),
        content: TextField(
          controller: controller,
          autofocus: true,
          textCapitalization: TextCapitalization.words,
          decoration: const InputDecoration(labelText: 'Name'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, controller.text.trim()),
            child: const Text('Save'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (next == null || next.isEmpty || next == user.name || !mounted) return;

    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await auth.updateProfile(name: next);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Name updated.')),
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

  Future<void> _changePassword() async {
    final result = await showDialog<_PasswordForm>(
      context: context,
      builder: (ctx) => const _ChangePasswordDialog(),
    );
    if (result == null || !mounted) return;

    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<AuthState>().changePassword(
            currentPassword: result.current,
            password: result.next,
            passwordConfirmation: result.confirm,
          );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Password updated.')),
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

  Future<void> _openStudio() async {
    final auth = context.read<AuthState>();
    if (!auth.isLoggedIn) {
      await Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
      if (!mounted || !auth.isLoggedIn) return;
    }
    if (!mounted) return;
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const CreatorHomeScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthState>();
    final user = auth.user;

    if (!auth.isLoggedIn) {
      return Scaffold(
        body: DecoratedBox(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Color(0xFF16352F), LiveMixTheme.ink],
            ),
          ),
          child: SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(24, 16, 24, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const BrandMark(size: 40, compact: true),
                  const Spacer(),
                  Text(
                    'Your profile',
                    style: GoogleFonts.outfit(
                      color: LiveMixTheme.mist,
                      fontSize: 30,
                      fontWeight: FontWeight.w800,
                      letterSpacing: -0.7,
                    ),
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'Sign in to manage your photo, password, and Studio go-live.',
                    style: TextStyle(color: LiveMixTheme.mute, height: 1.45, fontSize: 15),
                  ),
                  const SizedBox(height: 28),
                  FilledButton(
                    onPressed: () async {
                      await Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => const LoginScreen()),
                      );
                    },
                    child: const Text('Sign in'),
                  ),
                  const SizedBox(height: 12),
                  OutlinedButton.icon(
                    onPressed: _openStudio,
                    icon: const Icon(Icons.mic_rounded),
                    label: const Text('Studio / Go live'),
                  ),
                  const SizedBox(height: 20),
                  const LegalLinks(dense: true),
                  const Spacer(flex: 2),
                ],
              ),
            ),
          ),
        ),
      );
    }

    final initials = _initials(user!.name.isNotEmpty ? user.name : user.email);

    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFF16352F), LiveMixTheme.ink, Color(0xFF0A0C10)],
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 8, 0),
                child: Row(
                  children: [
                    const Flexible(
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: BrandMark(compact: true),
                      ),
                    ),
                    const NetworkPill(),
                    IconButton(
                      tooltip: 'Refresh',
                      onPressed: _busy
                          ? null
                          : () async {
                              try {
                                await auth.refreshUser();
                              } catch (_) {}
                            },
                      icon: const Icon(Icons.refresh_rounded),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
                  children: [
                    Center(
                      child: Stack(
                        children: [
                          CircleAvatar(
                            radius: 48,
                            backgroundColor: LiveMixTheme.panelHi,
                            backgroundImage: user.avatarUrl != null &&
                                    user.avatarUrl!.isNotEmpty
                                ? NetworkImage(user.avatarUrl!)
                                : null,
                            child: user.avatarUrl == null ||
                                    user.avatarUrl!.isEmpty
                                ? Text(
                                    initials,
                                    style: GoogleFonts.outfit(
                                      color: LiveMixTheme.accentBright,
                                      fontSize: 28,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  )
                                : null,
                          ),
                          Positioned(
                            right: 0,
                            bottom: 0,
                            child: Material(
                              color: LiveMixTheme.accent,
                              shape: const CircleBorder(),
                              child: InkWell(
                                customBorder: const CircleBorder(),
                                onTap: _busy ? null : _pickAvatar,
                                child: const Padding(
                                  padding: EdgeInsets.all(8),
                                  child: Icon(
                                    Icons.camera_alt_rounded,
                                    size: 18,
                                    color: LiveMixTheme.ink,
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      user.name.isNotEmpty ? user.name : 'Your account',
                      textAlign: TextAlign.center,
                      style: GoogleFonts.outfit(
                        color: LiveMixTheme.mist,
                        fontSize: 24,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.4,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      user.email,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: LiveMixTheme.mute),
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 12),
                      Text(
                        _error!,
                        textAlign: TextAlign.center,
                        style: const TextStyle(color: LiveMixTheme.bad),
                      ),
                    ],
                    const SizedBox(height: 22),
                    FilledButton.icon(
                      onPressed: _busy ? null : _openStudio,
                      icon: const Icon(Icons.mic_rounded),
                      label: const Text('Studio / Go live'),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      Brand.studioPhoneBlurb,
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: LiveMixTheme.mute.withOpacity(0.95),
                        fontSize: 12.5,
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 24),
                    _SettingsCard(
                      children: [
                        _SettingsTile(
                          icon: Icons.badge_outlined,
                          title: 'Display name',
                          subtitle: user.name.isNotEmpty ? user.name : 'Add a name',
                          onTap: _busy ? null : _editName,
                        ),
                        _SettingsTile(
                          icon: Icons.lock_outline_rounded,
                          title: 'Change password',
                          subtitle: 'Update your login password',
                          onTap: _busy ? null : _changePassword,
                        ),
                        _SettingsTile(
                          icon: Icons.photo_camera_outlined,
                          title: 'Profile photo',
                          subtitle: 'Choose a new picture',
                          onTap: _busy ? null : _pickAvatar,
                        ),
                        _SettingsTile(
                          icon: Icons.mail_outline_rounded,
                          title: 'Email',
                          subtitle: user.email,
                          onTap: null,
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    const LegalLinks(),
                    TextButton(
                      onPressed: () =>
                          openExternalUrl('mailto:${AppConfig.supportEmail}'),
                      child: Text(
                        AppConfig.supportEmail,
                        style: const TextStyle(
                          color: LiveMixTheme.mute,
                          decoration: TextDecoration.underline,
                        ),
                      ),
                    ),
                    const SizedBox(height: 8),
                    OutlinedButton.icon(
                      onPressed: _busy
                          ? null
                          : () async {
                              await auth.logout();
                            },
                      icon: const Icon(Icons.logout_rounded),
                      label: const Text('Sign out'),
                    ),
                    const SizedBox(height: 28),
                    Text(
                      'Delete account',
                      style: GoogleFonts.outfit(
                        color: LiveMixTheme.bad,
                        fontWeight: FontWeight.w700,
                        fontSize: 16,
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Removes your login from Sound Mix Live. Required for store account-deletion policy.',
                      style: TextStyle(color: LiveMixTheme.mute, height: 1.4),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton(
                      onPressed: _busy ? null : _confirmDelete,
                      style: OutlinedButton.styleFrom(
                        foregroundColor: LiveMixTheme.bad,
                        side: const BorderSide(color: LiveMixTheme.bad),
                      ),
                      child: Text(_busy ? 'Working…' : 'Delete my account'),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _initials(String value) {
    final parts = value.trim().split(RegExp(r'\s+'));
    if (parts.length >= 2) {
      return '${parts[0][0]}${parts[1][0]}'.toUpperCase();
    }
    if (value.isNotEmpty) return value[0].toUpperCase();
    return '?';
  }
}

class _SettingsCard extends StatelessWidget {
  const _SettingsCard({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: LiveMixTheme.panel.withOpacity(0.92),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: LiveMixTheme.accent.withOpacity(0.16)),
      ),
      child: Column(children: children),
    );
  }
}

class _SettingsTile extends StatelessWidget {
  const _SettingsTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Icon(icon, color: LiveMixTheme.accentBright),
      title: Text(
        title,
        style: GoogleFonts.outfit(
          color: LiveMixTheme.mist,
          fontWeight: FontWeight.w600,
        ),
      ),
      subtitle: Text(
        subtitle,
        style: const TextStyle(color: LiveMixTheme.mute, fontSize: 13),
      ),
      trailing: onTap == null
          ? null
          : const Icon(Icons.chevron_right_rounded, color: LiveMixTheme.mute),
      onTap: onTap,
    );
  }
}

class _PasswordForm {
  const _PasswordForm({
    required this.current,
    required this.next,
    required this.confirm,
  });

  final String current;
  final String next;
  final String confirm;
}

class _ChangePasswordDialog extends StatefulWidget {
  const _ChangePasswordDialog();

  @override
  State<_ChangePasswordDialog> createState() => _ChangePasswordDialogState();
}

class _ChangePasswordDialogState extends State<_ChangePasswordDialog> {
  final _current = TextEditingController();
  final _next = TextEditingController();
  final _confirm = TextEditingController();
  String? _localError;

  @override
  void dispose() {
    _current.dispose();
    _next.dispose();
    _confirm.dispose();
    super.dispose();
  }

  void _submit() {
    if (_next.text.length < 8) {
      setState(() => _localError = 'Use at least 8 characters.');
      return;
    }
    if (_next.text != _confirm.text) {
      setState(() => _localError = 'Passwords do not match.');
      return;
    }
    Navigator.pop(
      context,
      _PasswordForm(
        current: _current.text,
        next: _next.text,
        confirm: _confirm.text,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      backgroundColor: LiveMixTheme.panel,
      title: const Text('Change password'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
            controller: _current,
            obscureText: true,
            autofillHints: const [AutofillHints.password],
            decoration: const InputDecoration(labelText: 'Current password'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _next,
            obscureText: true,
            autofillHints: const [AutofillHints.newPassword],
            decoration: const InputDecoration(labelText: 'New password'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _confirm,
            obscureText: true,
            autofillHints: const [AutofillHints.newPassword],
            decoration: const InputDecoration(labelText: 'Confirm new password'),
          ),
          if (_localError != null) ...[
            const SizedBox(height: 10),
            Text(_localError!, style: const TextStyle(color: LiveMixTheme.bad)),
          ],
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        TextButton(
          onPressed: _submit,
          child: const Text('Save'),
        ),
      ],
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
