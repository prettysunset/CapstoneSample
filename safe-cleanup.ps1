<#
Safe cleanup script
Removes the .kiosk_profile folder from the Git index, adds it to .gitignore,
commits and pushes the change. DOES NOT rewrite history or remove secrets from past commits.
Run in repository root using PowerShell:
  powershell -ExecutionPolicy Bypass -File .\safe-cleanup.ps1
#>

param(
    [string]$ProfileFolder = ".kiosk_profile",
    [string]$Remote = "origin"
)

try {
    Write-Output "Removing '$ProfileFolder' from git index..."
    git rm -r --cached $ProfileFolder 2>$null
} catch {
    Write-Output "git rm returned: $_"
}

if (-not (Test-Path .gitignore)) {
    New-Item -Path .gitignore -ItemType File -Force | Out-Null
}

$entry = "$ProfileFolder/"
$content = Get-Content .gitignore -ErrorAction SilentlyContinue
if ($content -notcontains $entry) {
    Add-Content .gitignore $entry
    git add .gitignore
    Write-Output "Added '$entry' to .gitignore"
} else {
    Write-Output "'.gitignore' already contains '$entry'"
}

Write-Output "Creating commit (if there are staged changes)..."
# Commit only if there is anything to commit
$status = git status --porcelain
if ($status) {
    git commit -m "Remove kiosk profile from repo and ignore it" -a
    Write-Output "Committed changes."
    Write-Output "Pushing to $Remote..."
    git push $Remote HEAD
    Write-Output "Pushed."
} else {
    Write-Output "Nothing to commit."
}

Write-Output "DONE. Reminder: this does NOT remove secrets from prior commits. If the secret was exposed in remote/history, rotate/revoke credentials immediately and consider rewriting history with git-filter-repo or BFG."