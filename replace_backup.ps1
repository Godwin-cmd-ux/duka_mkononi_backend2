$path = 'C:\Users\HP\Desktop\server\server.js'
$content = Get-Content $path -Raw

$oldStart = '// ✅ AUTOMATIC DATABASE BACKUP SYSTEM'
$startIdx = $content.IndexOf($oldStart)

Write-Host "Found start at index: $startIdx"

if ($startIdx -eq -1) {
    Write-Host "Could not find backup system start marker!"
    exit 1
}

$newContent = @"

// ✅ AUTOMATIC DATABASE BACKUP SYSTEM (Cloudinary)
// =============================================
let lastBackupTime = null;
let backupCount = 0;

// In-memory list of recent backup metadata (no local files)
const backupHistory = [];

/**
 * Escape a string value for SQL (single quotes -> doubled single quotes)
 */
function escapeSQLString(val) {
    if (val === null || val === undefined) return 'NULL';
    if (typeof val === 'number') return val.toString();
    if (typeof val === 'boolean') return val ? 'TRUE' : 'FALSE';
    if (val instanceof Date) {
        return `'` + val.toISOString() + `'`;
    }
    if (typeof val === 'object') {
        try {
            return `'` + JSON.stringify(val).replace(/'/g, "''") + `'`;
        } catch (e) {
            return `'` + String(val).replace(/'/g, "''") + `'`;
        }
    }
    return `'` + String(val).replace(/'/g, "''") + `'`;
}

/**
 * Known application tables discovered from the codebase.
 */
const KNOWN_TABLES = [
    'users', 'sales', 'sale_items', 'products', 'customers',
    'user_logs', 'password_reset_codes', 'pending_emails',
    'matangazo', 'reactions', 'notifications', 'payments',
    'expenses', 'user_presence_history', 'active_sessions'
];

/**
 * Try to get table names via RPC, otherwise fall back to known table list.
 */
async function discoverTableNames() {
    try {
        const { data, error } = await supabaseAdmin.rpc('get_public_tables');
        if (!error && data && data.length > 0) {
            const tables = data.map(t => typeof t === 'string' ? t : t.table_name || t).filter(Boolean);
            if (tables.length > 0) {
                console.log(`📚 Discovered ` + tables.length + ` tables via RPC`);
                return tables;
            }
        }
    } catch (e) {
        console.log('⚠️ RPC get_public_tables not available, using fallback');
    }
    
    console.log(`📚 Using known table list (` + KNOWN_TABLES.length + ` tables)`);
    const verified = [];
    for (const tableName of KNOWN_TABLES) {
        try {
            const { error } = await supabaseAdmin.from(tableName).select('*').limit(1);
            if (!error) {
                verified.push(tableName);
            } else {
                console.log(`  ⏭️ Table \"` + tableName + `\" does not exist or has RLS restrictions, skipping`);
            }
        } catch (e) {
            console.log(`  ⏭️ Table \"` + tableName + `\" check failed, skipping`);
        }
    }
    return verified;
}

/**
 * Get column names for a table by fetching a sample row.
 */
async function getTableColumns(tableName) {
    try {
        const { data, error } = await supabaseAdmin.from(tableName).select('*').limit(1);
        if (error) throw error;
        if (data && data.length > 0) {
            return Object.keys(data[0]);
        }
        return [];
    } catch (error) {
        throw new Error(`Cannot get columns for ` + tableName + `: ` + error.message);
    }
}

/**
 * Upload a buffer/string to Cloudinary as a raw file.
 */
function uploadToCloudinary(content, filename) {
    return new Promise((resolve, reject) => {
        const uploadStream = cloudinary.uploader.upload_stream(
            {
                resource_type: 'raw',
                public_id: 'backups/' + filename.replace('.sql', ''),
                folder: 'duka_mkononi_backups',
                format: 'sql',
                use_filename: true,
                unique_filename: false,
                overwrite: false
            },
            (error, result) => {
                if (error) {
                    reject(error);
                } else {
                    resolve(result);
                }
            }
        );
        uploadStream.end(Buffer.from(content, 'utf8'));
    });
}

/**
 * Perform a full database backup and upload to Cloudinary:
 * - Discover tables
 * - Generate INSERT-only SQL
 * - Upload the generated SQL to Cloudinary as a raw file
 */
async function performDatabaseBackup() {
    const startTime = Date.now();
    
    try {
        console.log('💾 Starting automatic database backup (Cloudinary)...');
        
        // 1. Discover table names
        const tableNames = await discoverTableNames();
        
        if (!tableNames || tableNames.length === 0) {
            console.warn('⚠️ No accessible tables found to back up');
            return {
                success: false,
                error: 'No accessible tables found'
            };
        }
        
        console.log(`📚 Backing up ` + tableNames.length + ` tables: ` + tableNames.join(', '));
        
        // 2. Generate SQL content
        const lines = [];
        lines.push('-- ============================================');
        lines.push('-- DukaMkononi Database Backup (Cloudinary)');
        lines.push('-- Generated: ' + new Date().toISOString());
        lines.push('-- Server: ' + supabaseUrl);
        lines.push('-- Tables: ' + tableNames.join(', '));
        lines.push('-- ============================================');
        lines.push('');
        lines.push('-- ⚠️ INSERT-only backup: preserves existing schema on restore');
        lines.push('');
        lines.push('BEGIN;');
        lines.push('');
        
        let totalRows = 0;
        
        for (const tableName of tableNames) {
            try {
                lines.push('-- ==========================================');
                lines.push('-- Table: ' + tableName);
                lines.push('-- ==========================================');
                
                let columnNames;
                try {
                    columnNames = await getTableColumns(tableName);
                } catch (colError) {
                    console.warn('⚠️ Could not get columns for ' + tableName + ': ' + colError.message);
                    lines.push('-- ⚠️ Could not get columns: ' + colError.message);
                    lines.push('');
                    continue;
                }
                
                const { data: rows, error: dataError } = await supabaseAdmin
                    .from(tableName)
                    .select('*')
                    .limit(1000000);
                
                if (dataError) {
                    console.warn('⚠️ Could not fetch data from ' + tableName + ': ' + dataError.message);
                    lines.push('-- ⚠️ Could not fetch data: ' + dataError.message);
                    lines.push('');
                    continue;
                }
                
                if (!rows || rows.length === 0) {
                    lines.push('-- No data in table "' + tableName + '"');
                    lines.push('');
                    continue;
                }
                
                const columnList = columnNames.map(c => '"' + c + '"').join(', ');
                const batchSize = 50;
                for (let i = 0; i < rows.length; i += batchSize) {
                    const batch = rows.slice(i, i + batchSize);
                    const valueStrings = batch.map(row => {
                        const values = columnNames.map(col => escapeSQLString(row[col]));
                        return '(' + values.join(', ') + ')';
                    });
                    
                    lines.push('INSERT INTO "' + tableName + '" (' + columnList + ') VALUES');
                    const valuesStr = valueStrings.join(',' + "\r\n");
                    lines.push(valuesStr + ';');
                }
                
                totalRows += rows.length;
                lines.push('');
                console.log('  ✅ ' + tableName + ': ' + rows.length + ' rows backed up');
                
            } catch (tableError) {
                console.warn('⚠️ Error backing up table ' + tableName + ': ' + tableError.message);
                lines.push('-- ⚠️ Error backing up table "' + tableName + '": ' + tableError.message);
                lines.push('');
            }
        }
        
        lines.push('COMMIT;');
        lines.push('');
        lines.push('-- ============================================');
        lines.push('-- Backup completed: ' + new Date().toISOString());
        lines.push('-- Total tables: ' + tableNames.length + ', Total rows: ' + totalRows);
        lines.push('-- ============================================');
        
        const sqlContent = lines.join("\r\n");
        
        // 3. Upload to Cloudinary
        const timestamp = new Date().toISOString()
            .replace(/T/, '_')
            .replace(/:/g, '-')
            .replace(/\.\d+Z/, '');
        const filename = 'backup_' + timestamp + '.sql';
        
        console.log('☁️ Uploading backup to Cloudinary...');
        const uploadResult = await uploadToCloudinary(sqlContent, filename);
        
        lastBackupTime = new Date();
        backupCount++;
        
        const duration = ((Date.now() - startTime) / 1000).toFixed(2);
        const sizeKB = (Buffer.byteLength(sqlContent, 'utf8') / 1024).toFixed(2);
        
        // Track in memory
        const backupMeta = {
            filename,
            cloudinary_url: uploadResult.secure_url,
            cloudinary_public_id: uploadResult.public_id,
            tables: tableNames.length,
            rows: totalRows,
            sizeKB: parseFloat(sizeKB),
            duration: parseFloat(duration),
            timestamp: new Date().toISOString()
        };
        backupHistory.unshift(backupMeta);
        if (backupHistory.length > 20) backupHistory.pop();
        
        console.log('✅ Backup #' + backupCount + ' uploaded to Cloudinary: ' + filename);
        console.log('   📊 Tables: ' + tableNames.length + ', Rows: ' + totalRows + ', Size: ' + sizeKB + ' KB');
        console.log('   ☁️  URL: ' + uploadResult.secure_url);
        console.log('   ⏱️  Duration: ' + duration + 's');
        
        return { success: true, ...backupMeta };
        
    } catch (error) {
        console.error('❌ Backup failed: ' + error.message);
        return {
            success: false,
            error: error.message,
            timestamp: new Date().toISOString()
        };
    }
}

// =============================================
// ✅ MANUAL BACKUP ENDPOINT
// =============================================
app.get('/api/backup', async (req, res) => {
    try {
        console.log('🔧 Manual backup requested...');
        const result = await performDatabaseBackup();
        
        if (result.success) {
            res.json({
                success: true,
                message: 'Database backup completed successfully and uploaded to Cloudinary',
                backup: result,
                backupCount,
                lastBackup: lastBackupTime?.toISOString() || null
            });
        } else {
            res.status(500).json({
                success: false,
                error: result.error,
                message: 'Database backup failed'
            });
        }
    } catch (error) {
        console.error('❌ Manual backup error: ' + error.message);
        res.status(500).json({
            success: false,
            error: error.message,
            message: 'Database backup failed'
        });
    }
});

// =============================================
// ✅ BACKUP STATUS ENDPOINT (Cloudinary-based)
// =============================================
app.get('/api/backup/status', (req, res) => {
    try {
        res.json({
            success: true,
            backupCount,
            lastBackup: lastBackupTime?.toISOString() || null,
            storage: 'Cloudinary',
            totalFiles: backupHistory.length,
            recentFiles: backupHistory.slice(0, 10)
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

// =============================================
// ✅ DOWNLOAD BACKUP ENDPOINT (proxy from Cloudinary)
// =============================================
app.get('/api/backup/download/:index', async (req, res) => {
    try {
        const index = parseInt(req.params.index);
        const backup = backupHistory[index];
        if (!backup || !backup.cloudinary_url) {
            return res.status(404).json({ error: 'Backup not found' });
        }
        // Redirect to the actual Cloudinary URL
        res.redirect(backup.cloudinary_url);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// =============================================
// ✅ SCHEDULE AUTOMATIC BACKUPS (every 24 hours)
// =============================================
// Run first backup 5 seconds after server starts
setTimeout(() => {
    console.log('🔄 Starting automatic backup schedule (Cloudinary, every 24 hours)...');
    performDatabaseBackup().catch(err => console.error('Initial backup failed: ' + err.message));
}, 5000);

// Then every 24 hours (86,400,000 ms)
setInterval(() => {
    console.log('🔄 Running scheduled backup...');
    performDatabaseBackup().catch(err => console.error('Scheduled backup failed: ' + err.message));
}, 24 * 60 * 60 * 1000);

console.log('✅ Database backup system (Cloudinary) initialized 🔄 backups run every 24 hours');

"@

$replacementStart = $content.Substring(0, $startIdx)
$remaining = $content.Substring($startIdx)

# Find the end of this backup section - look for the START SERVER section
$serverStartMarker = '// ✅ START SERVER - LISTEN ON PORT'
$serverIdx = $remaining.IndexOf($serverStartMarker)

if ($serverIdx -eq -1) {
    # Try alternative marker
    $serverIdx = $remaining.IndexOf('const PORT = process.env.PORT')
}

Write-Host "Found server start at index within remaining: $serverIdx"

if ($serverIdx -eq -1) {
    Write-Host "Could not find server start section!"
    exit 1
}

$finalContent = $replacementStart + $newContent + $remaining.Substring($serverIdx)

# Write the file
[System.IO.File]::WriteAllText($path, $finalContent, [System.Text.UTF8Encoding]::new($false))

Write-Host "✅ Backup section replaced successfully!"
Write-Host "File was written to: $path"

# Verify by checking for cloudinary references
$verify = Get-Content $path -Raw
if ($verify.Contains('cloudinary')) {
    Write-Host "✅ Cloudinary references found in updated file!"
} else {
    Write-Host "❌ Cloudinary references NOT found!"
}
