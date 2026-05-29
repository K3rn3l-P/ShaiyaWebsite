                    <?php
                    $searchGuild = isset($_GET['search_guild']) ? trim($_GET['search_guild']) : '';
                    ?>
                    <div class="guilds-section">
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px;">
                            <div style="display: flex; align-items: center; gap: 20px;">
                                <form action="/admin" method="GET" style="display: flex; gap: 10px;">
                                    <input type="hidden" name="view" value="Guilds">
                                    <div style="position: relative;">
                                        <i class="fas fa-search"
                                            style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #444; font-size: 13px;"></i>
                                        <input type="text" name="search_guild" class="char-input"
                                            value="<?php echo htmlspecialchars($searchGuild); ?>"
                                            placeholder="Search Guild..."
                                            style="background: rgba(255,255,255,0.02); border-color: #222; color: #fff; height: 38px; padding-left: 45px; width: 250px; font-size: 13px; border-radius: 4px;">
                                    </div>
                                    <button type="submit" class="btn-action btn-sm">SEARCH</button>
                                </form>
                            </div>
                        </div>

                        <div class="chars-table-container">
                            <table class="chars-table">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">ID</th>
                                        <th style="width: 80px;"></th>
                                        <th>Guild Name</th>
                                        <th>Guild Master</th>
                                        <th>Points</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $guildQuery = "SELECT GuildID, Country, GuildName, MasterName, MasterCharID, GuildPoint, CreateDate FROM PS_GameData.dbo.Guilds WHERE Del = 0";
                                    $guildParams = [];
                                    if ($searchGuild) {
                                        $guildQuery .= " AND GuildName LIKE ?";
                                        $guildParams[] = '%' . $searchGuild . '%';
                                    }
                                    $guildQuery .= " ORDER BY GuildPoint DESC";

                                    $guildResults = [];
                                    try {
                                        $q = $pdoAdmin->prepare($guildQuery);
                                        $q->execute($guildParams);
                                        $guildResults = $q->fetchAll(PDO::FETCH_ASSOC);
                                    } catch (Throwable $e) {}
                                    $found = false;
                                    foreach ($guildResults as $row) {
                                        $found = true;
                                        $factionImg = ($row['Country'] == 0) ? 'aol.webp' : 'uof.webp';
                                        $formattedDate = date('d M H:i', strtotime($row['CreateDate']));
                                        ?>
                                        <tr>
                                            <td style="color: #666;"><?php echo $row['GuildID']; ?></td>
                                            <td>
                                                <div class="faction-icon">
                                                    <img src="assets/<?php echo $factionImg; ?>"
                                                        style="width: 20px; vertical-align: middle;">
                                                </div>
                                            </td>
                                            <td>
                                                <a href="/admin?view=GUILD_OVERVIEW&id=<?php echo $row['GuildID']; ?>"
                                                    class="user-link"
                                                    style="color: #ccd; font-weight: 500; font-size: 14px; text-decoration: none;"><?php echo htmlspecialchars($row['GuildName']); ?></a>
                                            </td>
                                            <td>
                                                <a href="/admin?view=CharEdit&id=<?php echo $row['MasterCharID']; ?>"
                                                    class="user-link"><?php echo htmlspecialchars($row['MasterName']); ?></a>
                                            </td>
                                            <td style="font-family: 'Futura PT', sans-serif;">
                                                <?php echo number_format($row['GuildPoint']); ?>
                                            </td>
                                            <td style="color: #888; font-size: 13px;"><?php echo $formattedDate; ?></td>
                                        </tr>
                                        <?php
                                    }
                                    if (!$found) {
                                        echo '<tr><td colspan="6" style="text-align: center; padding: 50px; color: #444;">No guilds found.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

