<html>
<head>
    <title>OJT Profile</title>
    <link rel="stylesheet" type="text/css" href="stylesforojt.css">
    <script src="../js/ojt_profile.js"></script>
</head>
<body>
         <div class="sidebar">
    <div style="height:100%; display:flex; flex-direction:column; justify-content:space-between;">
      <div>
        <div style="text-align:center; padding: 8px 12px 20px;">
          <div style="width:76px;height:76px;margin:0 auto 8px;border-radius:50%;background:#ffffff22;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:24px;overflow:hidden;">
            JS
          </div>
          <h3 style="color:#fff;font-size:16px;margin-bottom:4px;">User Name</h3>
          <p style="color:#d6d9ee;font-size:13px;margin-top:0;">Role</p>
        </div>

        <nav style="padding: 6px 10px 12px;">
            <a href="ojt_home.php"
                 style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
                    <path d="M3 11.5L12 4l9 7.5"></path>
                    <path d="M5 12v7a1 1 0 0 0 1 1h3v-5h6v-5h3a1 1 0 0 0 1-1v-7"></path>
                </svg>
                <span style="font-weight:600;">Home</span>
            </a>

          <a href="ojt_profile.php" class="active" aria-current="page"
             style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#2f3459;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,0.04);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <span style="font-weight:600;">Profile</span>
          </a>

            <a href="#dtr" style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
              <rect x="3" y="4" width="18" height="18" rx="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span>DTR</span>
            </a>

            <a href="#reports" style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
              <rect x="3" y="3" width="4" height="18"></rect>
              <rect x="10" y="8" width="4" height="13"></rect>
              <rect x="17" y="13" width="4" height="8"></rect>
            </svg>
            <span>Reports</span>
          </a>
        </nav>
      </div>

      <div style="padding:14px 12px 26px;">
        <a href="/logout.php" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;text-decoration:none;color:#2f3459;background:#fff;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
            <path d="M16 17l5-5-5-5"></path>
            <path d="M21 12H9"></path>
            <path d="M9 19H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h4"></path>
          </svg>
          <span style="font-weight:600;">Logout</span>
        </a>
      </div>
    </div>
  </div>

    <div class="bottom-title">OJT-MS</div>
</div>
    <div class="main-content" style="position:fixed; left:260px; top:0; bottom:0; padding:32px 32px 32px 0; display:flex; flex-direction:column; align-items:flex-start; gap:20px; width:calc(100% - 260px); background:#f6f7fb; overflow:auto; font-size:18px;">
        <div style="width:auto; max-width:980px; align-self:flex-start; display:flex; gap:24px; align-items:center; background:#fff; padding:24px; border-radius:12px; box-shadow:0 6px 20px rgba(47,52,89,0.06);">
            <div style="width:110px;height:110px;border-radius:50%;background:#2f3459;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:36px;">JS</div>
            <div style="flex:0; white-space:nowrap;">
                <h1 style="margin:0 0 6px 0; font-size:26px; color:#2f3459;">Jasmine Santiago</h1>
                <p style="margin:0 0 8px 0; color:#6b6f8b; font-size:16px;">Active OJT • Mayor's Office</p>
                <div style="display:flex; gap:12px; align-items:center; margin-top:6px;">
                    <button style="padding:12px 16px; border-radius:10px; border:0; background:#2f3459; color:#fff; cursor:pointer; font-size:15px;">Print DTR</button>
                    <button style="padding:12px 16px; border-radius:10px; border:1px solid #e6e9f2; background:transparent; color:#2f3459; cursor:pointer; font-size:15px;">Edit Profile</button>
                </div>
            </div>
        </div>
            <div style="max-width:980px; width:100%; display:grid; grid-template-columns:1fr; gap:20px;">
                    <div style="background:#fff; padding:20px; border-radius:12px; box-shadow:0 6px 20px rgba(47,52,89,0.04);">
                    <div style="display:flex; flex-direction:column; gap:12px;">
                            <!-- Tabs -->
                            <div role="tablist" aria-label="Profile tabs" style="display:flex; gap:8px; flex-wrap:wrap;">
                            <button class="tab-btn active" data-tab="tab-info" aria-selected="true" style="padding:10px 14px; border-radius:8px; border:0; background:#2f3459; color:#fff; cursor:pointer; font-size:15px;">Information</button>
                            <button class="tab-btn" data-tab="tab-journals" aria-selected="false" style="padding:10px 14px; border-radius:8px; border:1px solid #e6e9f2; background:transparent; color:#2f3459; cursor:pointer; font-size:15px;">Weekly Journals</button>
                            <button class="tab-btn" data-tab="tab-attachments" aria-selected="false" style="padding:10px 14px; border-radius:8px; border:1px solid #e6e9f2; background:transparent; color:#2f3459; cursor:pointer; font-size:15px;">Attachments</button>
                            <button class="tab-btn" data-tab="tab-eval" aria-selected="false" style="padding:10px 14px; border-radius:8px; border:1px solid #e6e9f2; background:transparent; color:#2f3459; cursor:pointer; font-size:15px;">Evaluation</button>
                            </div>

                            <!-- Tab panels -->
                            <div style="border-radius:8px; padding:14px; background:#fbfcff; min-height:220px;">
                            <section id="tab-info" class="tab-panel active" style="display:block;">
                                    <h4 style="margin:0 0 10px 0; color:#2f3459; font-size:20px;">Information</h4>

                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap;">
                                        <div style="flex:1 1 280px; min-width:220px;">
                                            <p style="margin:0; color:#6b6f8b; line-height:1.6; font-size:16px;">
                                            Age: 20<br>
                                            Birthday: <b>11/06/2005</b><br>
                                            Address: <b>123 Mabini St., Malolos</b><br>
                                            Phone: <b>09121363383</b><br>
                                            Email: <b>jasmine.santiago@example.com</b>
                                            </p>
                                        </div>

                                        <!-- Percent counter for OJT Home (fixed size) with hours and dates -->
                                        <div id="ojt-percent" style="flex:0 0 auto; display:flex;align-items:center;gap:12px;">
                                            <div class="ojt-circle" data-percent="72" style="width:62px;height:62px;border-radius:50%;
                                                     display:flex;align-items:center;justify-content:center;color:#2f3459;font-weight:700;font-size:14px;
                                                     background:conic-gradient(#2f3459 0deg, #e6e9f2 0deg);">
                                                72%
                                            </div>

                                            <div style="display:flex; flex-direction:column; align-items:flex-start; gap:4px; min-width:140px;">
                                                <div style="color:#2f3459;font-weight:700;font-size:14px;">180 of 500 hours</div>
                                                <div style="color:#6b6f8b;font-size:13px;">72% complete</div>
                                                <div style="color:#6b6f8b;font-size:13px; margin-top:6px;">Date Started: <b style="color:#2f3459;">July 21, 2025</b></div>
                                                <div style="color:#6b6f8b;font-size:13px;">Expected End Date: <b style="color:#2f3459;">November 13, 2025</b></div>
                                            </div>
                                        </div>
                                    </div>

                                    <script>
                                    (function(){
                                        // Set or compute the percent value here (replace 72 with dynamic value if available)
                                        var percent = 72;
                                        var circle = document.querySelector('#tab-info .ojt-circle');
                                        var label = document.querySelector('#tab-info #ojt-percent div:last-child');
                                        if (circle) {
                                            var deg = Math.max(0, Math.min(100, percent)) * 3.6;
                                            circle.style.background = 'conic-gradient(#2f3459 0deg ' + deg + 'deg, #e6e9f2 ' + deg + 'deg 360deg)';
                                            circle.textContent = percent + '%';
                                        }
                                        if (label) label.textContent = percent + ' complete';
                                    })();
                                    </script>
                            </section>

                            <section id="tab-journals" class="tab-panel" style="display:none;">
                                    <h4 style="margin:0 0 10px 0; color:#2f3459; font-size:20px;">Weekly Journals</h4>
                                    <p style="margin:0 0 8px 0; color:#6b6f8b; font-size:16px;">Recent weekly journals:</p>
                                    <ol style="margin:8px 0 0 18px; color:#6b6f8b; font-size:16px;">
                                    <li>Week 1 — Orientation and initial tasks</li>
                                    <li>Week 2 — Shadowing and basic assignments</li>
                                    <li>Week 3 — Assisted in community outreach</li>
                                    </ol>
                            </section>

                            <section id="tab-attachments" class="tab-panel" style="display:none;">
                                    <h4 style="margin:0 0 10px 0; color:#2f3459; font-size:20px;">Attachments</h4>
                                    <p style="margin:0 0 8px 0; color:#6b6f8b; font-size:16px;">Uploaded files and documents:</p>
                                    <ul style="margin:8px 0 0 18px; color:#6b6f8b; font-size:16px;">
                                    <li>Resume.pdf — uploaded 2025-05-28</li>
                                    <li>Medical_Clearance.pdf — uploaded 2025-05-29</li>
                                    </ul>
                            </section>

                            <section id="tab-eval" class="tab-panel" style="display:none;">
                                    <h4 style="margin:0 0 10px 0; color:#2f3459; font-size:20px;">Evaluation</h4>
                                    <p style="margin:0; color:#6b6f8b; font-size:16px;">Supervisor evaluations and ratings will appear here. No evaluations recorded yet.</p>
                            </section>
                            </div>
                    </div>
                    </div>
            </div>

            <script>
                    (function(){
                    const tabs = document.querySelectorAll('.tab-btn');
                    const panels = document.querySelectorAll('.tab-panel');
                    function activate(targetBtn){
                            const target = targetBtn.dataset.tab;
                            tabs.forEach(btn=>{
                            const isActive = btn === targetBtn;
                            btn.classList.toggle('active', isActive);
                            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                            btn.style.background = isActive ? '#2f3459' : 'transparent';
                            btn.style.color = isActive ? '#fff' : '#2f3459';
                            btn.style.border = isActive ? '0' : '1px solid #e6e9f2';
                            });
                            panels.forEach(p=>{
                            p.style.display = p.id === target ? 'block' : 'none';
                            });
                    }
                    tabs.forEach(btn => btn.addEventListener('click', ()=> activate(btn)));
                    })();
            </script>
            </div>
    </div>
</body>
</html>