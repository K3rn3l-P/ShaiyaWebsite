                    <?php
                    $fCharName = isset($_GET['f_charname']) ? trim($_GET['f_charname']) : '';
                    $fFaction  = isset($_GET['f_faction'])  ? trim($_GET['f_faction'])  : '';
                    $fOnline   = isset($_GET['f_online'])   ? trim($_GET['f_online'])   : '';
                    $charsPage    = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
                    $charsPerPage = 50;
                    $charsOffset  = ($charsPage - 1) * $charsPerPage;
                    $charsWhere   = ["C.Del = 0"];
                    $charsParams  = [];
                    if ($fCharName !== '') {
                        $charsWhere[] = "C.CharName LIKE ?";
                        $charsParams[] = '%' . $fCharName . '%';
                    }
                    if ($fFaction !== '' && in_array($fFaction, ['0', '1'], true)) {
                        $charsWhere[] = "MG.Country = ?";
                        $charsParams[] = (int) $fFaction;
                    }
                    if ($fOnline === '1') {
                        $charsWhere[] = "C.LoginStatus = 1";
                    }
                    $charsWhereSql = "WHERE " . implode(" AND ", $charsWhere);
                    $charsTotalCount = 0;
                    $charsTotalPages = 1;
                    $charRows = [];
                    try {
                        $ccStmt = $pdoAdmin->prepare("SELECT COUNT(*) as Total FROM PS_GameData.dbo.Chars C LEFT JOIN PS_GameData.dbo.UserMaxGrow MG ON MG.UserUID = C.UserUID $charsWhereSql");
                        $ccStmt->execute($charsParams);
                        $charsTotalCount = (int) ($ccStmt->fetch(PDO::FETCH_ASSOC)['Total'] ?? 0);
                        $charsTotalPages = max(1, (int) ceil($charsTotalCount / $charsPerPage));
                        $charsSql = "SELECT C.CharID, C.CharName, C.Level, C.Map, C.Money, C.LoginStatus, C.UserUID, MG.Country as Faction, U.UserID
                            FROM PS_GameData.dbo.Chars C
                            LEFT JOIN PS_GameData.dbo.UserMaxGrow MG ON MG.UserUID = C.UserUID
                            LEFT JOIN PS_UserData.dbo.Users_Master U ON U.UserUID = C.UserUID
                            $charsWhereSql
                            ORDER BY C.CharID DESC
                            OFFSET $charsOffset ROWS FETCH NEXT $charsPerPage ROWS ONLY";
                        $chStmt = $pdoAdmin->prepare($charsSql);
                        $chStmt->execute($charsParams);
                        $charRows = $chStmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {}
                    ?>
                    <div class="chars-table-container">
                        <form method="GET" id="charsHeaderFilters">
                            <input type="hidden" name="view" value="CHARS">
                            <table class="chars-table">
                                <thead>
                                    <tr>
                                        <th style="width: 70px;">Faction
                                            <select name="f_faction" class="table-filter-select js-auto-submit-onchange">
                                                <option value="">All</option>
                                                <option value="0" <?php echo $fFaction === '0' ? 'selected' : ''; ?>>AOL</option>
                                                <option value="1" <?php echo $fFaction === '1' ? 'selected' : ''; ?>>UOF</option>
                                            </select>
                                        </th>
                                        <th>Char Name
                                            <input type="text" name="f_charname" class="table-filter-input" placeholder="Search..." value="<?php echo htmlspecialchars($fCharName); ?>">
                                        </th>
                                        <th style="width: 60px;">Lvl</th>
                                        <th style="width: 60px;">Map</th>
                                        <th style="width: 130px;">Account</th>
                                        <th style="width: 80px;">Online
                                            <select name="f_online" class="table-filter-select js-auto-submit-onchange">
                                                <option value="">All</option>
                                                <option value="1" <?php echo $fOnline === '1' ? 'selected' : ''; ?>>Online</option>
                                            </select>
                                        </th>
                                        <th style="width: 80px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($charRows as $cr): ?>
                                    <tr>
                                        <td style="text-align: center;">
                                            <?php if (isset($cr['Faction'])): ?>
                                                <img src="assets/<?php echo $cr['Faction'] == 0 ? 'aol.webp' : 'uof.webp'; ?>" style="width: 20px;" alt="faction">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="/admin?view=CharEdit&id=<?php echo $cr['CharID']; ?>" class="user-link">
                                                <?php echo htmlspecialchars($cr['CharName']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo (int) $cr['Level']; ?></td>
                                        <td><?php echo (int) $cr['Map']; ?></td>
                                        <td>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $cr['UserUID']; ?>" class="user-link">
                                                <?php echo htmlspecialchars((string) ($cr['UserID'] ?? '')); ?>
                                            </a>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php echo (int) ($cr['LoginStatus'] ?? 0) === 1 ? '<span style="color:#48bb78;">&#9679;</span>' : '<span style="color:#444;">&#9679;</span>'; ?>
                                        </td>
                                        <td>
                                            <a href="/admin?view=CharEdit&id=<?php echo $cr['CharID']; ?>" class="btn-action btn-sm">EDIT</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (count($charRows) === 0): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 60px; color: #444;">
                                            <i class="fas fa-user-slash" style="font-size: 30px; opacity: 0.15; margin-bottom: 15px; display: block;"></i>
                                            No characters found.
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </form>
                        <?php if ($charsTotalPages > 1): ?>
                        <div class="pagination" style="display: flex; justify-content: center; gap: 10px; margin-top: 20px; align-items: center;">
                            <?php if ($charsPage > 1): ?>
                                <a href="/admin?view=CHARS&p=<?php echo $charsPage - 1; ?><?php echo $fCharName !== '' ? '&f_charname=' . urlencode($fCharName) : ''; ?><?php echo $fFaction !== '' ? '&f_faction=' . $fFaction : ''; ?><?php echo $fOnline !== '' ? '&f_online=' . $fOnline : ''; ?>" class="btn-search" style="text-decoration: none; padding: 8px 15px;">&laquo; Prev</a>
                            <?php endif; ?>
                            <span style="color: #888; padding: 8px;">Page <?php echo $charsPage; ?> of <?php echo $charsTotalPages; ?></span>
                            <?php if ($charsPage < $charsTotalPages): ?>
                                <a href="/admin?view=CHARS&p=<?php echo $charsPage + 1; ?><?php echo $fCharName !== '' ? '&f_charname=' . urlencode($fCharName) : ''; ?><?php echo $fFaction !== '' ? '&f_faction=' . $fFaction : ''; ?><?php echo $fOnline !== '' ? '&f_online=' . $fOnline : ''; ?>" class="btn-search" style="text-decoration: none; padding: 8px 15px;">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

