                    <?php
                    $searchItem = isset($_GET['search_item']) ? trim($_GET['search_item']) : '';
                    ?>
                    <div class="char-edit-section">
                        <div
                            style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px;">
                            <div>
                                <p style="color: #666; font-size: 13px;">Send items directly to a player's GiftBox
                                    (Bank/Mall storage).</p>
                            </div>

                        </div>

                        <div style="display: grid; grid-template-columns: 350px 1fr; gap: 40px; align-items: start;">
                            <!-- Column 1: Send Item Form -->
                            <div
                                style="background: rgba(255,255,255,0.02); padding: 35px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                                <h4
                                    style="color: #fff; margin-bottom: 30px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;">
                                    SEND ITEM</h4>
                                <form action="/admin_actions" method="POST" style="display: block;">
                                    <input type="hidden" name="action" value="giftbox">
                                    <input type="hidden" name="return_view" value="GIFTBOX">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                    <div style="display: flex; flex-direction: column; gap: 20px; margin-bottom: 30px;">
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                            <div class="char-input-group" style="display: block;">
                                                <label
                                                    style="display: block; color: #888; font-size: 11px; margin-bottom: 8px; text-transform: uppercase;">By
                                                    UserID</label>
                                                <input type="text" name="target_user" class="char-input"
                                                    placeholder="UserID..." value="<?php echo isset($_GET['target_user']) ? htmlspecialchars($_GET['target_user']) : ''; ?>"
                                                    style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; height: 40px;">
                                            </div>
                                            <div class="char-input-group" style="display: block;">
                                                <label
                                                    style="display: block; color: #888; font-size: 11px; margin-bottom: 8px; text-transform: uppercase;">By
                                                    Char Name</label>
                                                <input type="text" name="target_char" class="char-input"
                                                    placeholder="CharName..."
                                                    style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; height: 40px;">
                                            </div>
                                        </div>

                                        <div style="display: grid; grid-template-columns: 1fr 80px; gap: 15px;">
                                            <div class="char-input-group" style="display: block;">
                                                <label
                                                    style="display: block; color: #888; font-size: 11px; margin-bottom: 8px; text-transform: uppercase;">ItemID</label>
                                                <input type="number" name="itemid" id="gift_itemid" class="char-input"
                                                    placeholder="Paste ID here..." required
                                                    style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; height: 40px;">
                                            </div>
                                            <div class="char-input-group" style="display: block;">
                                                <label
                                                    style="display: block; color: #888; font-size: 11px; margin-bottom: 8px; text-transform: uppercase;">Count</label>
                                                <input type="number" name="count" class="char-input" value="1" required
                                                    style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; height: 40px; text-align: center;">
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn-action">SEND TO GIFTBOX</button>
                                    <div
                                        style="margin-top: 20px; padding: 15px; background: rgba(0,0,0,0.2); border-radius: 4px; border: 1px solid rgba(255,255,255,0.03);">
                                        <p style="color: #666; font-size: 11px; line-height: 1.5; margin: 0;">Provide either
                                            UserID or Char Name. ItemID is required. Items are sent to the Mall storage tab.
                                        </p>
                                    </div>
                                </form>
                            </div>

                            <!-- Column 2: Item Search Helper -->
                            <div
                                style="background: rgba(0,0,0,0.2); border-radius: 12px; border: 1px solid rgba(232, 200, 129, 0.05); padding: 30px; display: flex; flex-direction: column; min-height: 500px;">
                                <h4
                                    style="color: #fff; margin-bottom: 25px; font-size: 15px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;">
                                    ITEM SEARCH
                                    <i class="fas fa-search" style="color: #666; font-size: 16px;"></i>
                                </h4>

                                <form action="/admin" method="GET" style="margin-bottom: 25px;">
                                    <input type="hidden" name="view" value="Giftbox">
                                    <div style="display: flex; gap: 10px;">
                                        <input type="text" name="search_item" class="char-input"
                                            value="<?php echo htmlspecialchars($searchItem); ?>"
                                            placeholder="Search ItemName..."
                                            style="background: rgba(255,255,255,0.03); border-color: #333; color: #fff; height: 40px;">
                                        <button type="submit" class="btn-action btn-sm"><i
                                                class="fas fa-search"></i></button>
                                    </div>
                                </form>

                                <div class="blacklist-scroll"
                                    style="flex-grow: 1; max-height: 350px; overflow-y: auto; padding-right: 10px;">
                                    <?php if ($searchItem): ?>
                                        <?php
                                        $searchLike = '%' . $searchItem . '%';
                                        $itemSearchResults = [];
                                        try {
                                            $q = $pdoAdmin->prepare("SELECT TOP 30 ItemID, ItemName FROM PS_GameDefs.dbo.Items WHERE ItemName LIKE ? ORDER BY ItemName ASC");
                                            $q->execute([$searchLike]);
                                            $itemSearchResults = $q->fetchAll(PDO::FETCH_ASSOC);
                                        } catch (Throwable $e) {}
                                        $found = false;
                                        foreach ($itemSearchResults as $iRow) {
                                            $found = true;
                                            echo '<div class="js-set-input-value js-hover-border" data-target-id="gift_itemid" data-value="' . (int) $iRow['ItemID'] . '" data-border-hover="#e8c881" data-border-default="rgba(255,255,255,0.05)" style="background: rgba(255,255,255,0.02); padding: 12px 15px; border: 1px solid rgba(255,255,255,0.05); border-radius: 6px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; cursor: pointer; transition: all 0.2s;">';
                                            echo '<span><span style="color: #e8c881; font-weight: 500;">' . htmlspecialchars($iRow['ItemName']) . '</span> <span style="color: #555; font-size: 11px; margin-left: 10px;">ID: ' . $iRow['ItemID'] . '</span></span>';
                                            echo '<i class="fas fa-plus-circle" style="color: #333; font-size: 14px;"></i>';
                                            echo '</div>';
                                        }
                                        if (!$found) {
                                            echo '<div style="text-align: center; padding: 50px 0; color: #444;">No items found.</div>';
                                        }
                                        ?>
                                    <?php else: ?>
                                        <div style="text-align: center; padding: 80px 0; color: #444;">
                                            <i class="fas fa-box-open"
                                                style="font-size: 40px; margin-bottom: 20px; opacity: 0.2;"></i><br>
                                            Search for items to get IDs.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

