@include('partials.dm-locale')
<!DOCTYPE html>
<html lang="{{ session('locale', 'sw') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Backups - DukaMkononi Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="sa-shell">
@include('system_admin.partials.sidebar', ['saActive' => 'backup'])

<main class="page-content">
    <div class="card" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px;">
        <div>
            <div class="header-title">Backups</div>
            <div class="header-subtitle">
                Nightly snapshots and backups induced on demand, filed in Cloudinary.
                The schema is constant; the seed (data) is refetched on every run.
            </div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <form method="POST"
                  action="{{ route('system_admin.backup.induce') }}"
                  onsubmit="return confirm('Take a backup of the database right now?\n\nEvery table is dumped and filed in Cloudinary as an induced backup, separate from the nightly one, so this never replaces it.');">
                @csrf
                <button type="submit" class="btn btn-primary">⚡ Induce Backup</button>
            </form>
            <a href="{{ route('system_admin.backup.index') }}" class="btn btn-ghost">⟳ Refresh</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-ok" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-err" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    @if ($tableWarning)
        <div class="alert alert-err" style="margin-bottom:16px;">
            <strong>Backups cannot be listed.</strong> {{ $tableWarning }}
            <div style="margin-top:6px;font-size:12px;">
                Apply <code>SYSTEM_SCHEMA.sql</code> (or run the backups migration) on the
                database this app reads, then refresh.
            </div>
        </div>
    @endif

    @if ($cloudinaryWarning)
        <div class="alert alert-err" style="margin-bottom:16px;">
            <strong>Cloudinary could not be read:</strong> {{ $cloudinaryWarning }}
            This list shows only what the database remembers, so a backup taken but not
            recorded may be missing here.
        </div>
    @endif

    @if ($imported > 0)
        <div class="alert alert-info" style="margin-bottom:16px;">
            {{ $imported }} {{ \Illuminate\Support\Str::plural('backup', $imported) }} found in Cloudinary
            that the database did not list yet — added below.
        </div>
    @endif

    @php
        $human = function (int $bytes): string {
            if ($bytes <= 0) return '—';
            if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
            if ($bytes >= 1024) return number_format($bytes / 1024) . ' KB';
            return $bytes . ' B';
        };

        $dayLabel = fn (string $stamp) => \Carbon\Carbon::parse(substr($stamp, 0, 10))->format('D, d M Y');
    @endphp

    {{-- Summary: same three figures as the reference page --}}
    <div class="stats">
        <div class="stat">
            <div class="stat-value">{{ number_format(count($days)) }}</div>
            <div class="stat-label">{{ $filtered ? 'Backups in range' : 'Backups stored' }}</div>
        </div>
        <div class="stat">
            <div class="stat-value" style="font-size:18px;color:#047857;">
                {{ $latest ? $dayLabel($latest) : '—' }}
            </div>
            <div class="stat-label">Latest backup</div>
        </div>
        <div class="stat">
            <div class="stat-value" style="font-size:18px;">
                {{ $human($filtered ? $matchingBytes : $totalBytes) }}
            </div>
            <div class="stat-label">Stored in Cloudinary</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card">
        <form method="GET" action="{{ route('system_admin.backup.index') }}"
              style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <div>
                <label style="display:block;font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#64748b;margin-bottom:5px;">From</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label style="display:block;font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#64748b;margin-bottom:5px;">To</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            @if ($filtered)
                <a href="{{ route('system_admin.backup.index') }}" class="btn btn-ghost">Reset</a>
                <span style="font-size:12px;color:#64748b;padding-bottom:10px;">
                    showing {{ count($days) }} of {{ number_format($totalStored) }} backups
                </span>
            @endif
        </form>
    </div>

    <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:14px 18px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
            <span style="font-size:13px;font-weight:800;color:#0f172a;">Backups by date</span>
            <span style="font-size:11px;color:#64748b;">
                Nightly <code style="background:#f1f5f9;padding:2px 5px;border-radius:5px;">{{ $cloudinaryFolder }}/backup_&lt;date&gt;/</code>
                · Induced <code style="background:#f1f5f9;padding:2px 5px;border-radius:5px;">{{ $cloudinaryFolder }}/induced/backup_&lt;date&gt;_&lt;time&gt;/</code>
            </span>
        </div>

        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Schema</th>
                        <th>Seed (data)</th>
                        <th style="text-align:right;">Rows</th>
                        <th style="text-align:right;">Took</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($days as $day)
                        <tr>
                            <td>
                                <span style="font-weight:700;color:#0f172a;">{{ $dayLabel($day->stamp) }}</span>
                                @if ($day->time)
                                    <span style="font-weight:400;color:#64748b;font-size:11.5px;">{{ $day->time }}</span>
                                @endif
                                <div style="font-size:11px;color:#94a3b8;margin-top:2px;">
                                    @if ($day->created_at)
                                        filed {{ \Carbon\Carbon::parse($day->created_at)->setTimezone('Africa/Dar_es_Salaam')->format('d M Y, H:i') }} EAT
                                    @else
                                        found in Cloudinary
                                    @endif
                                </div>
                                @if ($day->kind === 'induced')
                                    <span class="badge b-info" style="margin-top:5px;">induced</span>
                                @endif
                                @if ($day->status === 'imported')
                                    <span class="badge b-warn" style="margin-top:5px;">imported</span>
                                @endif
                            </td>
                            <td>
                                @if ($day->schema_bytes > 0)
                                    <span>{{ $human($day->schema_bytes) }}</span>
                                    <a href="{{ route('system_admin.backup.download', [$day->stamp, 'schema']) }}"
                                       style="margin-left:8px;color:#4f46e5;font-size:11.5px;font-weight:600;text-decoration:none;">download</a>
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($day->seed_bytes > 0)
                                    <span>{{ $human($day->seed_bytes) }}</span>
                                    <span style="font-size:10.5px;color:#94a3b8;margin-left:3px;">gz</span>
                                    <a href="{{ route('system_admin.backup.download', [$day->stamp, 'seed']) }}"
                                       style="margin-left:8px;color:#4f46e5;font-size:11.5px;font-weight:600;text-decoration:none;">download</a>
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                {{ $day->rows_count > 0 ? number_format($day->rows_count) : '—' }}
                                @if ($day->tables_count > 0)
                                    <span style="display:block;font-size:11px;color:#94a3b8;">{{ $day->tables_count }} tables</span>
                                @endif
                            </td>
                            <td style="text-align:right;color:#475569;">
                                {{ $day->seconds !== null ? number_format($day->seconds, 1) . 's' : '—' }}
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;">
                                    <a href="{{ route('system_admin.backup.download', [$day->stamp, 'seed']) }}"
                                       title="Download the data for {{ $day->stamp }}"
                                       style="color:#4f46e5;text-decoration:none;font-size:15px;">⬇</a>
                                    <form method="POST"
                                          action="{{ route('system_admin.backup.destroy', $day->stamp) }}"
                                          onsubmit="return confirm('Delete the backup for {{ $day->stamp }} permanently?\n\nBoth SQL files are removed from Cloudinary and the record is removed from the database. This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Delete the backup for {{ $day->stamp }}"
                                                style="background:none;border:0;color:#dc2626;cursor:pointer;font-size:15px;">🗑</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:44px 16px;text-align:center;color:#94a3b8;">
                                @if ($filtered)
                                    No backups between {{ $filters['date_from'] ?: 'the beginning' }}
                                    and {{ $filters['date_to'] ?: 'today' }}.
                                    <a href="{{ route('system_admin.backup.index') }}" style="color:#4f46e5;">Clear the filter</a>.
                                @else
                                    <div style="font-weight:700;color:#64748b;">No backups yet.</div>
                                    <div style="font-size:12px;margin-top:4px;">
                                        The nightly snapshot is taken at midnight ({{ $scheduleTimezone }}), or press
                                        <strong>Induce Backup</strong> to take one now.
                                        Backups already in Cloudinary appear here on refresh.
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (count($days) > 0)
            <div style="padding:12px 18px;border-top:1px solid #e2e8f0;background:#f8fafc;font-size:11.5px;color:#64748b;">
                To restore one: run its <code style="background:#fff;padding:1px 5px;border-radius:4px;">schema_{{ $latest }}.sql</code>
                first, then <code style="background:#fff;padding:1px 5px;border-radius:4px;">seed_{{ $latest }}.sql</code>.
            </div>
        @endif
    </div>
</main>

<script>
    (function () {
        // The download/delete routes are authorized server-side, so the page
        // hands the signed-in JWT to the server in a same-site cookie. It is
        // the same token localStorage already holds; the cookie is only for
        // this origin and only for a day.
        var token = localStorage.getItem('userToken');
        var user = null;

        try {
            user = JSON.parse(localStorage.getItem('userData') || 'null');
        } catch (e) {
            user = null;
        }

        if (!token || !user) {
            window.location.href = '/login?role=msimamizi';
            return;
        }

        if (['super_admin', 'system_admin'].indexOf(user.role || '') === -1) {
            window.location.href = '/home';
            return;
        }

        document.cookie = 'dm_admin_token=' + token + '; path=/; max-age=86400; samesite=lax';
    })();
</script>
</body>
</html>
