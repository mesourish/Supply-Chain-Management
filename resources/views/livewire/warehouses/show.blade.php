<?php

use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\BinProductStock;
use App\Models\Product;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    // Warehouse selection
    public $selectedWarehouseId = null;
    public $activeTab = 'floor_plan';       // floor_plan | bin_stock | product_locator | transfers

    // Bin CRUD
    public $showBinModal = false;
    public $editingBinId = null;
    public $bin_code = '';
    public $bin_zone = '';
    public $bin_aisle = '';
    public $bin_rack = '';
    public $bin_shelf = '';
    public $bin_type = 'standard';
    public $bin_max_weight = '';
    public $bin_notes = '';

    // Stock modal
    public $showStockModal = false;
    public $stockBinId = null;
    public $stockProductId = null;
    public $stockQty = 0;
    public $stockNote = '';

    // Transfer modal
    public $showTransferModal = false;
    public $txProductId = null;
    public $txFromBinId = null;
    public $txToBinId = null;
    public $txQty = 1;
    public $txReason = '';

    // Filters
    public $filterZone = '';
    public $filterRack = '';
    public $filterBinType = '';
    public $searchProduct = '';

    // Zone viewer
    public $selectedZone = null;

    // Warehouse edit
    public $showWarehouseModal = false;
    public $wh_name = '';
    public $wh_location = '';
    public $wh_contact_name = '';
    public $wh_contact_phone = '';
    public $wh_contact_email = '';
    public $wh_address = '';

    public function mount($id)
    {
        $this->selectedWarehouseId = $id;
        $this->loadZones();
        $this->loadZoneTabsForWarehouse();
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->filterZone = '';
        $this->filterRack = '';
        $this->filterBinType = '';
    }

    // ─── Bin CRUD ──────────────────────────────────────────────────
    public function openBinModal($id = null)
    {
        $this->resetBinForm();
        if ($id) {
            $bin = WarehouseBin::findOrFail($id);
            $this->editingBinId = $id;
            $this->bin_code     = $bin->bin_code;
            $this->bin_zone     = $bin->zone ?? '';
            $this->bin_aisle    = $bin->aisle ?? '';
            $this->bin_rack     = $bin->rack ?? '';
            $this->bin_shelf    = $bin->shelf ?? '';
            $this->bin_type     = $bin->bin_type;
            $this->bin_max_weight = $bin->max_weight_kg ?? '';
            $this->bin_notes    = $bin->notes ?? '';
        }
        $this->showBinModal = true;
    }

    public function saveBin()
    {
        $this->validate([
            'bin_code'  => 'required|string|max:50',
            'bin_zone'  => 'nullable|string|max:10',
            'bin_rack'  => 'nullable|string|max:20',
        ]);

        $data = [
            'warehouse_id' => $this->selectedWarehouseId,
            'bin_code'     => $this->bin_code,
            'zone'         => $this->bin_zone ?: null,
            'aisle'        => $this->bin_aisle ?: null,
            'rack'         => $this->bin_rack ?: null,
            'shelf'        => $this->bin_shelf ?: null,
            'bin_type'     => $this->bin_type,
            'max_weight_kg' => $this->bin_max_weight ?: null,
            'notes'        => $this->bin_notes ?: null,
            'is_active'    => true,
        ];

        if ($this->editingBinId) {
            WarehouseBin::findOrFail($this->editingBinId)->update($data);
            session()->flash('success', 'Bin updated.');
        } else {
            WarehouseBin::create($data);
            session()->flash('success', 'Bin created.');
        }
        $this->showBinModal = false;
        $this->resetBinForm();
    }

    public function deleteBin($id)
    {
        $bin = WarehouseBin::findOrFail($id);
        if ($bin->stockEntries()->where('quantity', '>', 0)->exists()) {
            session()->flash('error', 'Cannot delete bin — it still has stock. Transfer stock first.');
            return;
        }
        $bin->delete();
        session()->flash('success', 'Bin deleted.');
    }

    public function toggleBinActive($id)
    {
        $bin = WarehouseBin::findOrFail($id);
        $bin->update(['is_active' => !$bin->is_active]);
    }

    private function resetBinForm()
    {
        $this->editingBinId = null;
        $this->bin_code = $this->bin_zone = $this->bin_aisle = '';
        $this->bin_rack = $this->bin_shelf = $this->bin_notes = '';
        $this->bin_type = 'standard';
        $this->bin_max_weight = '';
    }

    // ─── Stock Adjustment ──────────────────────────────────────────
    public function openStockModal($binId)
    {
        $this->stockBinId = $binId;
        $this->stockProductId = null;
        $this->stockQty = 0;
        $this->stockNote = '';
        $this->showStockModal = true;
    }

    public function saveStockAdjustment()
    {
        $this->validate([
            'stockProductId' => 'required|exists:products,id',
            'stockQty'       => 'required|integer|not_in:0',
        ]);

        $entry = BinProductStock::firstOrNew([
            'warehouse_bin_id' => $this->stockBinId,
            'product_id'       => $this->stockProductId,
        ]);
        $entry->quantity = max(0, ($entry->quantity ?? 0) + (int)$this->stockQty);
        $entry->save();

        // Log in inventory_transactions
        \App\Models\InventoryTransaction::create([
            'product_id'      => $this->stockProductId,
            'to_bin_id'       => (int)$this->stockQty > 0 ? $this->stockBinId : null,
            'from_bin_id'     => (int)$this->stockQty < 0 ? $this->stockBinId : null,
            'type'            => 'adjustment',
            'quantity'        => abs((int)$this->stockQty),
            'notes'           => $this->stockNote ?: 'Manual stock adjustment',
            'user_id'         => auth()->id(),
        ]);

        $this->showStockModal = false;
        session()->flash('success', 'Stock adjusted.');
    }

    // ─── Bin Transfer ──────────────────────────────────────────────
    public function openTransferModal($binId = null, $productId = null)
    {
        $this->txFromBinId = $binId;
        $this->txProductId = $productId;
        $this->txToBinId   = null;
        $this->txQty       = 1;
        $this->txReason    = '';
        $this->showTransferModal = true;
    }

    public function saveTransfer()
    {
        $this->validate([
            'txProductId' => 'required|exists:products,id',
            'txFromBinId' => 'required|exists:warehouse_bins,id',
            'txToBinId'   => 'required|exists:warehouse_bins,id|different:txFromBinId',
            'txQty'       => 'required|integer|min:1',
        ]);

        $fromEntry = BinProductStock::where('warehouse_bin_id', $this->txFromBinId)
            ->where('product_id', $this->txProductId)->first();

        if (!$fromEntry || $fromEntry->quantity < $this->txQty) {
            session()->flash('error', 'Insufficient stock in source bin.');
            return;
        }

        // Deduct from source
        $fromEntry->decrement('quantity', $this->txQty);

        // Add to destination
        $toEntry = BinProductStock::firstOrNew([
            'warehouse_bin_id' => $this->txToBinId,
            'product_id'       => $this->txProductId,
        ]);
        $toEntry->quantity = ($toEntry->quantity ?? 0) + $this->txQty;
        $toEntry->save();

        // Create transfer record
        \App\Models\BinTransfer::create([
            'reference_no'   => 'TXF-' . now()->format('YmdHis') . '-' . rand(100,999),
            'product_id'     => $this->txProductId,
            'from_bin_id'    => $this->txFromBinId,
            'to_bin_id'      => $this->txToBinId,
            'quantity'       => $this->txQty,
            'reason'         => $this->txReason,
            'transferred_by' => auth()->id(),
        ]);

        // Log inventory transaction
        \App\Models\InventoryTransaction::create([
            'product_id'  => $this->txProductId,
            'from_bin_id' => $this->txFromBinId,
            'to_bin_id'   => $this->txToBinId,
            'type'        => 'transfer',
            'quantity'    => $this->txQty,
            'notes'       => 'Bin transfer: ' . $this->txReason,
            'user_id'     => auth()->id(),
        ]);

        $this->showTransferModal = false;
        session()->flash('success', 'Stock transferred successfully.');
    }

    public function with()
    {
        $warehouses = Warehouse::withCount('bins')->get();
        $warehouse  = Warehouse::with('bins')->withCount('bins')->find($this->selectedWarehouseId);

        
        $racksByZone = [];
        if ($this->selectedWarehouseId) {
            $bins = WarehouseBin::where('warehouse_id', $this->selectedWarehouseId)
                ->when(isset($this->searchBin) && $this->searchBin, fn($q) => $q->where('bin_code', 'like', "%{$this->searchBin}%"))
                ->orderBy('zone')->orderBy('rack')->orderBy('shelf')->orderBy('aisle')
                ->get();
            foreach ($bins as $bin) {
                $zone = $bin->zone ?? 'NO-ZONE';
                $rack = $bin->rack ?? 'NO-RACK';
                if (!isset($racksByZone[$zone])) $racksByZone[$zone] = [];
                if (!isset($racksByZone[$zone][$rack])) $racksByZone[$zone][$rack] = [];
                $racksByZone[$zone][$rack][] = $bin;
            }
        }

        // Get zones for this warehouse
        $zones = WarehouseBin::where('warehouse_id', $this->selectedWarehouseId)
            ->whereNotNull('zone')
            ->distinct('zone')
            ->orderBy('zone')
            ->pluck('zone');

        // Bins query with filters
        $binsQuery = WarehouseBin::with(['stockEntries.product'])
            ->where('warehouse_id', $this->selectedWarehouseId)
            ->when($this->filterZone, fn($q) => $q->where('zone', $this->filterZone))
            ->when($this->filterRack, fn($q) => $q->where('rack', $this->filterRack))
            ->when($this->filterBinType, fn($q) => $q->where('bin_type', $this->filterBinType))
            ->orderBy('zone')->orderBy('aisle')->orderBy('rack')->orderBy('shelf');

        // Product locator
        $productLocatorResults = collect();
        if ($this->activeTab === 'product_locator' && strlen($this->searchProduct) >= 2) {
            $productLocatorResults = BinProductStock::with(['bin.warehouse', 'product'])
                ->where('quantity', '>', 0)
                ->whereHas('bin', fn($q) => $q->where('warehouse_id', $this->selectedWarehouseId))
                ->whereHas('product', fn($q) => $q->where('name', 'like', "%{$this->searchProduct}%")
                                                    ->orWhere('sku', 'like', "%{$this->searchProduct}%"))
                ->get();
        }

        // Rack summary for floor plan
        $rackSummary = WarehouseBin::select('zone', 'rack',
                \DB::raw('COUNT(*) as bin_count'),
                \DB::raw('SUM(is_active) as active_bins'))
            ->where('warehouse_id', $this->selectedWarehouseId)
            ->when($this->filterZone, fn($q) => $q->where('zone', $this->filterZone))
            ->groupBy('zone', 'rack')
            ->orderBy('zone')->orderBy('rack')
            ->get();

        // Stock per bin for floor plan
        $binStockQty = BinProductStock::whereHas('bin', fn($q) => $q->where('warehouse_id', $this->selectedWarehouseId))
            ->select('warehouse_bin_id', \DB::raw('SUM(quantity) as total_qty'))
            ->groupBy('warehouse_bin_id')
            ->pluck('total_qty', 'warehouse_bin_id');

        $binProductCount = BinProductStock::whereHas('bin', fn($q) => $q->where('warehouse_id', $this->selectedWarehouseId))
            ->where('quantity', '>', 0)
            ->select('warehouse_bin_id', \DB::raw('COUNT(DISTINCT product_id) as product_count'))
            ->groupBy('warehouse_bin_id')
            ->pluck('product_count', 'warehouse_bin_id');

        $binStockMap = $binStockQty->map(fn($qty, $binId) => [
            'qty'      => $qty,
            'products' => $binProductCount[$binId] ?? 0,
        ]);

        // All products for modals
        $allProducts = Product::orderBy('name')->get(['id', 'name', 'sku', 'unit_of_measure']);

        // All bins for transfer modal
        $allBins = WarehouseBin::where('warehouse_id', $this->selectedWarehouseId)
            ->where('is_active', true)
            ->orderBy('zone')->orderBy('rack')->orderBy('shelf')
            ->get();

        $pageBins = $binsQuery->paginate(20);

        // Warehouse KPIs
        $totalUnits     = BinProductStock::whereHas('bin', fn($q) => $q->where('warehouse_id', $this->selectedWarehouseId))->sum('quantity') ?? 0;
        $totalSKUs      = BinProductStock::whereHas('bin', fn($q) => $q->where('warehouse_id', $this->selectedWarehouseId))->where('quantity','>',0)->distinct('product_id')->count('product_id');
        $totalBins      = WarehouseBin::where('warehouse_id', $this->selectedWarehouseId)->count();
        $occupiedBins   = BinProductStock::whereHas('bin', fn($q) => $q->where('warehouse_id', $this->selectedWarehouseId))->where('quantity','>',0)->distinct('warehouse_bin_id')->count();
        $lowStockBins   = BinProductStock::whereHas('bin', fn($q) => $q->where('warehouse_id', $this->selectedWarehouseId))
            ->where('quantity', '>', 0)->where('quantity', '<', 10)->count();

        // Zone list for Zone tab
        $zoneTableData = [];
        foreach ($this->zoneTabs as $z) {
            $binCount = WarehouseBin::where('warehouse_id', $this->selectedWarehouseId)
                ->where('zone', $z)->count();
            $rackCount = WarehouseBin::where('warehouse_id', $this->selectedWarehouseId)
                ->where('zone', $z)->distinct('rack')->count('rack');
            $zoneTableData[] = ['name' => $z, 'bins' => $binCount, 'racks' => $rackCount];
        }

        return compact(
            'warehouses', 'warehouse', 'zones', 'pageBins', 'rackSummary', 'binStockMap',
            'allProducts', 'allBins', 'productLocatorResults',
            'totalUnits', 'totalSKUs', 'totalBins', 'occupiedBins', 'lowStockBins', 'zoneTableData', 'racksByZone'
        );
    }

    // --- MERGED FROM BIN MAINTENANCE ---



    public $activeZoneTab         = null;         // active zone letter
    public $zoneTabs              = [];

    // ─── RACK MODAL (Add / Edit) ─────────────────────────────
    public $showRackModal  = false;
    public $isEditMode     = false;
    public $editingRackId  = null;
    public $isStagingRack  = false;

    // Form fields mapped exactly to reference system
    public $ra_zone         = '';       // add_storage_area.code = WAREHOUSE_ZONE
    public $ra_zone_new     = '';       // for when adding a new zone directly from rack modal
    public $ra_rack_name    = '';       // add_storage_area.unit_number = Rack Name
    public $ra_capacity     = 1;        // auto-computed from dimensions
    public $ra_height_start = 1;        // rack_height_start (rows)
    public $ra_height_end   = 3;        // rack_height_end
    public $ra_depth_start  = 1;        // rack_depth_start (columns)
    public $ra_depth_end    = 3;        // rack_depth_end
    public $ra_max_weight   = 1000;
    public $ra_bin_type     = 'standard'; // standard | cold | bulk

    // ─── BIN STATUS MODAL ────────────────────────────────────
    public $showBinStatusModal = false;
    public $editingBinDbId     = null;
    public $binNewStatus       = 'ACTIVE';   // ACTIVE | INACTIVE | PICK_FACE
    public $binStatusProducts  = []; // To show product details
    public $binAddProductId    = null;
    public $binAddProductQty   = 1;
    public $allProductsList    = [];

    // ─── ZONE CRUD ───────────────────────────────────────────
    public $showZoneModal       = false;
    public $editingZoneId       = null;
    public $zone_name           = '';
    public $zoneList            = [];



    // ─── FILTERS ─────────────────────────────────────────────
    public $searchBin = '';



    // ─── COMPUTED CAPACITY ───────────────────────────────────
    public function updatedRaHeightStart() { $this->recalcCapacity(); }
    public function updatedRaHeightEnd()   { $this->recalcCapacity(); }
    public function updatedRaDepthStart()  { $this->recalcCapacity(); }
    public function updatedRaDepthEnd()    { $this->recalcCapacity(); }
    public function updatedIsStagingRack() { if ($this->isStagingRack) { $this->ra_height_start = $this->ra_height_end = $this->ra_depth_start = $this->ra_depth_end = 1; } $this->recalcCapacity(); }

    private function recalcCapacity()
    {
        $rows = max(0, (int)$this->ra_height_end - (int)$this->ra_height_start + 1);
        $cols = max(0, (int)$this->ra_depth_end  - (int)$this->ra_depth_start  + 1);
        $this->ra_capacity = $rows * $cols;
    }

    // ─── WAREHOUSE SELECTION ─────────────────────────────────
    

    private function loadZoneTabsForWarehouse()
    {
        if (!$this->selectedWarehouseId) { $this->zoneTabs = []; return; }
        $zones = WarehouseBin::where('warehouse_id', $this->selectedWarehouseId)
            ->whereNotNull('zone')
            ->distinct('zone')
            ->orderBy('zone')
            ->pluck('zone')
            ->toArray();
        // Add zones from zone list too
        foreach ($this->zoneList as $z) {
            if (!in_array($z, $zones)) $zones[] = $z;
        }
        sort($zones);
        $this->zoneTabs = array_values(array_unique($zones));
        if (!$this->activeZoneTab && count($this->zoneTabs) > 0) {
            $this->activeZoneTab = $this->zoneTabs[0];
        }
    }

    public function setActiveZone($zone) { $this->activeZoneTab = $zone; }

    // ─── ZONE CRUD ───────────────────────────────────────────
    private function loadZones()
    {
        // Load zone names from unique zones in warehouse_bins for this warehouse, preserving manually added ones
        $dbZones = WarehouseBin::where('warehouse_id', $this->selectedWarehouseId)
            ->whereNotNull('zone')
            ->distinct('zone')->pluck('zone')->toArray();
        $this->zoneList = array_values(array_unique(array_merge($this->zoneList ?? [], $dbZones)));
        sort($this->zoneList);
    }

    public function openAddZone()
    {
        $this->editingZoneId = null;
        $this->zone_name = '';
        $this->showZoneModal = true;
    }

    public function openEditZone($zone)
    {
        $this->editingZoneId = $zone;
        $this->zone_name     = $zone;
        $this->showZoneModal = true;
    }

    public function saveZone()
    {
        $this->validate(['zone_name' => 'required|string|max:20']);
        
        $zone = strtoupper(trim($this->zone_name));

        if ($this->editingZoneId) {
            // Rename zone on all bins for THIS warehouse only
            WarehouseBin::where('warehouse_id', $this->selectedWarehouseId)
                ->where('zone', $this->editingZoneId)
                ->update(['zone' => $zone]);
            
            // Also update it in zoneList if it exists
            $idx = array_search($this->editingZoneId, $this->zoneList);
            if ($idx !== false) {
                $this->zoneList[$idx] = $zone;
            }
        } else {
            // Add to zone list manually
            if (!in_array($zone, $this->zoneList)) {
                $this->zoneList[] = $zone;
            }
        }
        
        $this->activeZoneTab = $zone;

        $this->showZoneModal = false;
        $this->zone_name = '';
        $this->loadZones();
        if ($this->selectedWarehouseId) $this->loadZoneTabsForWarehouse();
        session()->flash('success', 'Zone saved.');
    }

    public function deleteZone($zone)
    {
        $count = WarehouseBin::where('zone', $zone)->count();
        if ($count > 0) {
            session()->flash('error', "Cannot delete zone '{$zone}' — it has {$count} bins. Remove the bins first.");
            return;
        }
        session()->flash('success', "Zone '{$zone}' removed.");
        $this->loadZones();
    }

    // ─── RACK / BIN MODAL ────────────────────────────────────
    public function openAddRack()
    {
        $this->isEditMode     = false;
        $this->editingRackId  = null;
        $this->isStagingRack  = false;
        $this->ra_zone        = $this->activeZoneTab ?? '';
        $this->ra_zone_new    = '';
        $this->ra_rack_name   = '';
        $this->ra_height_start = 1;
        $this->ra_height_end   = 3;
        $this->ra_depth_start  = 1;
        $this->ra_depth_end    = 3;
        $this->ra_bin_type     = 'standard';
        $this->ra_max_weight   = 1000;
        $this->recalcCapacity();
        $this->showRackModal  = true;
    }

    public function openEditRack($rackIdentifier)
    {
        // rackIdentifier = "zone|rack_name" string
        [$zone, $rackName] = explode('|', $rackIdentifier);
        $bins = WarehouseBin::where('warehouse_id', $this->selectedWarehouseId)
            ->where('zone', $zone)
            ->where('rack', $rackName)
            ->orderBy('shelf')->orderBy('aisle')
            ->get();

        if ($bins->isEmpty()) return;

        $first = $bins->first();
        $this->isEditMode      = true;
        $this->editingRackId   = $rackIdentifier;
        $this->isStagingRack   = $first->bin_type === 'staging';
        $this->ra_zone         = $zone;
        $this->ra_rack_name    = $rackName;
        $this->ra_bin_type     = $first->bin_type;
        $this->ra_max_weight   = $first->max_weight_kg ?? 1000;

        // Reverse-engineer height/depth from existing bins
        $shelves = $bins->pluck('shelf')->unique()->sort()->values();
        $aisles  = $bins->pluck('aisle')->unique()->sort()->values();
        $this->ra_height_start = 1;
        $this->ra_height_end   = max(1, $shelves->count());
        $this->ra_depth_start  = 1;
        $this->ra_depth_end    = max(1, $aisles->count());
        $this->recalcCapacity();
        $this->showRackModal = true;
    }

    public function saveRack()
    {
        $rules = [
            'ra_zone'      => 'required|string|max:20',
            'ra_rack_name' => 'required|string|max:30',
        ];
        if ($this->ra_zone === 'NEW') {
            $rules['ra_zone_new'] = 'required|string|max:20';
        }
        $this->validate($rules);

        if (!$this->selectedWarehouseId) {
            session()->flash('error', 'Select a warehouse first.');
            return;
        }

        $zone     = strtoupper(trim($this->ra_zone === 'NEW' ? $this->ra_zone_new : $this->ra_zone));
        $rackName = strtoupper(trim($this->ra_rack_name));
        $binType  = $this->isStagingRack ? 'staging' : $this->ra_bin_type;

        if ($this->isEditMode && $this->editingRackId) {
            // Delete old bins for this rack to regenerate with new dimensions
            [$oldZone, $oldRack] = explode('|', $this->editingRackId);
            WarehouseBin::where('warehouse_id', $this->selectedWarehouseId)
                ->where('zone', $oldZone)
                ->where('rack', $oldRack)
                ->delete();
        }

        // Generate bins based on height (rows/shelves) and depth (columns/aisles)
        $rows    = max(1, (int)$this->ra_height_end - (int)$this->ra_height_start + 1);
        $cols    = max(1, (int)$this->ra_depth_end  - (int)$this->ra_depth_start  + 1);
        $seq     = 1;

        for ($row = 1; $row <= $rows; $row++) {
            for ($col = 1; $col <= $cols; $col++) {
                $shelf    = 'S' . $row;
                $aisle    = str_pad($col, 2, '0', STR_PAD_LEFT);
                $binCode  = $zone . '-' . $rackName . '-' . $shelf . '-' . $aisle;

                // Check uniqueness within warehouse
                $suffix = '';
                $attempt = $binCode . $suffix;
                while (WarehouseBin::where('warehouse_id', $this->selectedWarehouseId)
                    ->where('bin_code', $attempt)->exists()) {
                    $suffix = '-' . rand(1, 99);
                    $attempt = $binCode . $suffix;
                }

                WarehouseBin::create([
                    'warehouse_id'    => $this->selectedWarehouseId,
                    'bin_code'        => $attempt,
                    'zone'            => $zone,
                    'aisle'           => $aisle,
                    'rack'            => $rackName,
                    'shelf'           => $shelf,
                    'bin_type'        => $binType,
                    'max_weight_kg'   => $this->ra_max_weight,
                    'bin_sequence_no' => $seq++,
                    'is_active'       => false,
                    'bin_status'      => 'INACTIVE',
                    'pick_face_flag'  => false,
                ]);
            }
        }

        $this->showRackModal = false;
        $this->loadZoneTabsForWarehouse();
        $this->activeZoneTab = $zone;
        session()->flash('success', "Rack {$zone}-{$rackName} saved with {$rows}×{$cols} = {$this->ra_capacity} bins.");
    }

    public function deleteRack($rackIdentifier)
    {
        [$zone, $rackName] = explode('|', $rackIdentifier);
        $count = WarehouseBin::where('warehouse_id', $this->selectedWarehouseId)
            ->where('zone', $zone)->where('rack', $rackName)->count();

        WarehouseBin::where('warehouse_id', $this->selectedWarehouseId)
            ->where('zone', $zone)->where('rack', $rackName)
            ->delete();

        session()->flash('success', "Rack {$zone}-{$rackName} deleted ({$count} bins removed).");
        $this->loadZoneTabsForWarehouse();
    }

    // ─── BIN STATUS ──────────────────────────────────────────
    public function openBinStatus($binId)
    {
        $bin = WarehouseBin::find($binId);
        if (!$bin) return;
        $this->editingBinDbId = $binId;
        $this->binNewStatus   = $bin->bin_status ?? 'ACTIVE';
        $this->binStatusProducts = \App\Models\BinProductStock::with('product')
            ->where('warehouse_bin_id', $binId)
            ->where('quantity', '>', 0)
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'name' => $item->product->name ?? 'Unknown',
                    'sku' => $item->product->sku ?? '-',
                    'qty' => $item->quantity,
                    'uom' => $item->product->unit_of_measure ?? 'units'
                ];
            })->toArray();
        if (empty($this->allProductsList)) {
            $this->allProductsList = \App\Models\Product::orderBy('name')->get(['id', 'name', 'sku'])->toArray();
        }
        $this->showBinStatusModal = true;
    }

    public function addProductToBin()
    {
        $this->validate([
            'binAddProductId' => 'required|exists:products,id',
            'binAddProductQty' => 'required|numeric|min:1',
        ]);

        $stock = \App\Models\BinProductStock::firstOrCreate(
            ['warehouse_bin_id' => $this->editingBinDbId, 'product_id' => $this->binAddProductId],
            ['quantity' => 0]
        );
        $stock->quantity += $this->binAddProductQty;
        $stock->save();

        \App\Models\InventoryTransaction::create([
            'product_id'  => $this->binAddProductId,
            'to_bin_id'   => $this->editingBinDbId,
            'type'        => 'adjustment',
            'quantity'    => $this->binAddProductQty,
            'notes'       => 'Added from bin maintenance',
            'user_id'     => auth()->id(),
        ]);

        $this->binAddProductId = null;
        $this->binAddProductQty = 1;

        // Refresh the product list shown in the modal
        $this->openBinStatus($this->editingBinDbId);
        
        session()->flash('bin_success', 'Product added to bin successfully.');
    }

    public function saveBinStatus()
    {
        $bin = WarehouseBin::find($this->editingBinDbId);
        if (!$bin) return;

        $bin->update([
            'bin_status'     => $this->binNewStatus,
            'is_active'      => $this->binNewStatus === 'ACTIVE',
            'pick_face_flag' => $this->binNewStatus === 'PICK_FACE',
        ]);

        $this->showBinStatusModal = false;
        session()->flash('success', "Bin {$bin->bin_code} → {$this->binNewStatus}");
    }

    // ─── WAREHOUSE EDIT ──────────────────────────────────────────
    public function openEditWarehouse()
    {
        $wh = Warehouse::find($this->selectedWarehouseId);
        if (!$wh) return;
        $this->wh_name            = $wh->name;
        $this->wh_location        = $wh->location ?? '';
        $this->wh_contact_name    = $wh->contact_person_name ?? '';
        $this->wh_contact_phone   = $wh->contact_number ?? '';
        $this->wh_contact_email   = $wh->contact_email ?? '';
        $this->wh_address         = $wh->address ?? '';
        $this->showWarehouseModal = true;
    }

    public function saveWarehouse()
    {
        $this->validate(['wh_name' => 'required|string|max:100']);
        
        Warehouse::where('id', $this->selectedWarehouseId)->update([
            'name'                => $this->wh_name,
            'location'            => $this->wh_location,
            'contact_person_name' => $this->wh_contact_name,
            'contact_number'      => $this->wh_contact_phone,
            'contact_email'       => $this->wh_contact_email,
            'address'             => $this->wh_address,
        ]);
        
        $this->showWarehouseModal = false;
        session()->flash('success', 'Warehouse details updated.');
    }



};
?>

<div class="min-h-screen bg-gray-50">

    {{-- Flash messages --}}
    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="fixed top-4 right-4 z-50 flex items-center gap-2 bg-green-600 text-white px-5 py-3 rounded-xl shadow-xl text-sm font-medium">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="fixed top-4 right-4 z-50 flex items-center gap-2 bg-red-600 text-white px-5 py-3 rounded-xl shadow-xl text-sm font-medium">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- ── Page Header ────────────────────────────────────────────── --}}
    <div class="bg-white border-b border-gray-200 px-6 py-4">
        <div class="max-w-screen-2xl mx-auto flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Warehouse Management</h1>
                <p class="text-sm text-gray-500 mt-0.5">Bin / Rack / Zone · Stock · Transfers · Reports</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('warehouses.index') }}"
                   class="flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition mr-auto">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to Warehouses
                </a>
                <button wire:click="openTransferModal()"
                        class="flex items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    Transfer Stock
                </button>
                <button wire:click="openBinModal()"
                        class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Bin
                </button>
            </div>
        </div>
    </div>

    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 py-6 space-y-6">

        @if($warehouse)
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-900">🏭 {{ $warehouse->name }}</h2>
                <span class="px-3 py-1 bg-indigo-100 text-indigo-800 text-xs font-semibold rounded-full border border-indigo-200">
                    {{ number_format($warehouse->bins_count) }} bins
                </span>
            </div>
            {{-- Contact Info Bar --}}
            <div class="bg-indigo-50 border border-indigo-100 rounded-lg px-5 py-3 flex flex-wrap gap-6 text-sm text-indigo-900">
                @if($warehouse->contact_person_name)
                    <div class="flex items-center gap-2"><span class="opacity-70">👤</span> <span class="font-medium">{{ $warehouse->contact_person_name }}</span></div>
                @endif
                @if($warehouse->contact_number)
                    <div class="flex items-center gap-2"><span class="opacity-70">📞</span> <span>{{ $warehouse->contact_number }}</span></div>
                @endif
                @if($warehouse->contact_email)
                    <div class="flex items-center gap-2"><span class="opacity-70">📧</span> <span>{{ $warehouse->contact_email }}</span></div>
                @endif
                @if($warehouse->address || $warehouse->location)
                    <div class="flex items-center gap-2"><span class="opacity-70">📍</span> <span>{{ $warehouse->address ?: $warehouse->location }}</span></div>
                @endif
                @if(!$warehouse->contact_person_name && !$warehouse->contact_number && !$warehouse->contact_email && !$warehouse->address && !$warehouse->location)
                    <div class="opacity-60 italic">No contact details added for this warehouse yet. Click 'Edit Selected' to add them.</div>
                @endif
            </div>
        @endif

        {{-- ── KPI Cards ───────────────────────────────────────────── --}}
        @if($warehouse)
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            @php
                $kpis = [
                    ['label'=>'Total Bins', 'value'=> number_format($totalBins), 'icon'=>'🗃️', 'color'=>'blue'],
                    ['label'=>'Occupied Bins', 'value'=> number_format($occupiedBins), 'icon'=>'📦', 'color'=>'green'],
                    ['label'=>'Empty Bins', 'value'=> number_format($totalBins - $occupiedBins), 'icon'=>'🕳️', 'color'=>'gray'],
                    ['label'=>'Total SKUs', 'value'=> number_format($totalSKUs), 'icon'=>'🏷️', 'color'=>'purple'],
                    ['label'=>'Total Units', 'value'=> number_format($totalUnits), 'icon'=>'📊', 'color'=>'indigo'],
                ];
                $colorMap = [
                    'blue'   => 'bg-blue-50 border-blue-200 text-blue-700',
                    'green'  => 'bg-green-50 border-green-200 text-green-700',
                    'gray'   => 'bg-gray-50 border-gray-200 text-gray-700',
                    'purple' => 'bg-purple-50 border-purple-200 text-purple-700',
                    'indigo' => 'bg-indigo-50 border-indigo-200 text-indigo-700',
                ];
            @endphp
            @foreach($kpis as $kpi)
                <div class="border rounded-xl p-4 {{ $colorMap[$kpi['color']] }}">
                    <p class="text-2xl mb-1">{{ $kpi['icon'] }}</p>
                    <p class="text-2xl font-bold">{{ $kpi['value'] }}</p>
                    <p class="text-xs font-medium opacity-75">{{ $kpi['label'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- ── View Tabs ───────────────────────────────────────────── --}}
        <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
            <div class="border-b border-gray-200 bg-gray-50 flex overflow-x-auto">
                @foreach([
                    ['floor_plan'      , '🗄️ Rack / Bin Maintenance'],
                    ['zones'           , '📍 Warehouse Zones'],
                    ['bin_stock'       , '📦 Bin Stock Detail'],
                    ['product_locator' , '🔍 Product Locator'],
                    ['transfers'       , '🔄 Transfer History'],
                ] as [$tab, $label])
                    <button wire:click="setTab('{{ $tab }}')"
                            class="px-5 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition
                                   {{ $activeTab === $tab
                                      ? 'border-indigo-600 text-indigo-700 bg-white'
                                      : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- ════════════════ TAB: FLOOR PLAN ════════════════ --}}
            @if($activeTab === 'floor_plan')


            @if($warehouse)
                {{-- Warehouse Info Card --}}
                <div class="rounded border mb-4 p-4" style="background:#f8f9fa;border-color:#dee2e6;">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-warehouse text-indigo-500"></i>
                            <h3 class="font-semibold text-gray-800">{{ $warehouse->name }}</h3>
                        </div>
                        <div class="flex gap-2">
                            <button wire:click="openEditWarehouse" title="Edit Warehouse"
                                    class="p-1.5 text-blue-600 hover:bg-blue-50 rounded">
                                <i class="fas fa-edit text-sm"></i>
                            </button>
                            <button wire:click="deleteWarehouse" wire:confirm="Delete this warehouse? All racks must be cleared first."
                                    title="Delete Warehouse"
                                    class="p-1.5 text-red-600 hover:bg-red-50 rounded">
                                <i class="fas fa-trash text-sm"></i>
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                        <div>
                            <span class="font-medium text-gray-600">Contact:</span>
                            <span class="text-gray-800 ml-1">{{ $warehouse->contact_person_name ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-600">Phone:</span>
                            <span class="text-gray-800 ml-1">{{ $warehouse->contact_number ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-600">Email:</span>
                            <span class="text-gray-800 ml-1">{{ $warehouse->contact_email ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-600">Address:</span>
                            <span class="text-gray-800 ml-1">{{ $warehouse->address ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Rack header + Add button --}}
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <h4 class="font-semibold text-gray-700">Rack Configuration</h4>
                        <input wire:model.live.debounce.400ms="searchBin" placeholder="Search bin..."
                               class="text-sm border rounded px-3 py-1.5" style="border-color:#ced4da;width:180px;">
                    </div>
                    <button wire:click="openAddRack"
                            class="flex items-center gap-2 px-4 py-2 text-sm text-white rounded font-medium"
                            style="background:#198754;">
                        <i class="fas fa-plus text-xs"></i> Add New Rack
                    </button>
                </div>

                {{-- Zone Tabs --}}
                @if(count($zoneTabs) > 0)
                <div class="mb-4">
                    <ul class="flex gap-1 border-b" style="border-color:#dee2e6;">
                        @foreach($zoneTabs as $zone)
                        <li>
                            <button wire:click="setActiveZone('{{ $zone }}')"
                                    class="px-4 py-2 text-sm font-medium rounded-t border-b-2 transition-colors"
                                    style="{{ $activeZoneTab === $zone
                                        ? 'border-color:#6366f1;color:#6366f1;background:#eef2ff;'
                                        : 'border-color:transparent;color:#6c757d;' }}">
                                Zone {{ $zone }}
                            </button>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                {{-- Racks in active zone --}}
                @php $activeRacks = $racksByZone[$activeZoneTab] ?? []; @endphp

                @if(empty($activeRacks) && $activeZoneTab)
                    <div class="text-center py-12 text-gray-400">
                        <i class="fas fa-inbox text-4xl mb-3"></i>
                        <p>No racks in Zone {{ $activeZoneTab }}. Click <strong>Add New Rack</strong> to create one.</p>
                    </div>
                @elseif(empty($zoneTabs))
                    <div class="text-center py-12 text-gray-400">
                        <i class="fas fa-layer-group text-4xl mb-3 text-indigo-300"></i>
                        <p class="font-medium">No racks configured yet.</p>
                        <p class="text-sm">Click <strong>Add New Rack</strong> to create your first rack with zones and bins.</p>
                    </div>
                @endif

                @foreach($activeRacks as $rackName => $bins)
                @php
                    $firstBin     = $bins[0];
                    $rackType     = $firstBin->bin_type ?? 'standard';
                    $isStaging    = $rackType === 'staging';
                    $activeCount  = collect($bins)->where('bin_status', 'ACTIVE')->count();
                    $inactiveCount= collect($bins)->where('bin_status', 'INACTIVE')->count();
                    $pickFaceCount= collect($bins)->where('bin_status', 'PICK_FACE')->count();
                    $totalBins    = count($bins);
                    // Group bins by shelf (row), then by aisle (column)
                    $byShelf = [];
                    foreach($bins as $b) {
                        $shelf = $b->shelf ?? 'S1';
                        $aisle = $b->aisle ?? '01';
                        if(!isset($byShelf[$shelf])) $byShelf[$shelf] = [];
                        $byShelf[$shelf][$aisle] = $b;
                    }
                    ksort($byShelf);
                @endphp
                <div class="mb-5 rounded-lg border shadow-sm bg-white overflow-hidden" style="border-color:#dee2e6;">
                    {{-- Rack Header --}}
                    <div class="flex items-center justify-between px-4 py-3 border-b"
                         style="background:{{ $isStaging ? '#fff3cd' : '#f8f9fa' }};border-color:#dee2e6;">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-th-list {{ $isStaging ? 'text-yellow-600' : 'text-indigo-500' }} text-sm"></i>
                                <span class="font-bold text-gray-800">{{ $activeZoneTab }}-{{ $rackName }}</span>
                                @if($isStaging)
                                    <span class="px-2 py-0.5 text-xs rounded-full font-medium"
                                          style="background:#ffc107;color:#000;">Staging</span>
                                @endif
                                <span class="text-xs text-gray-500 px-2 py-0.5 rounded"
                                      style="background:#e9ecef;">{{ ucfirst($rackType) }}</span>
                            </div>
                            <div class="flex gap-3 text-xs text-gray-500">
                                <span class="flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full inline-block" style="background:#198754;"></span>
                                    Active: {{ $activeCount }}
                                </span>
                                @if($pickFaceCount > 0)
                                <span class="flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full inline-block" style="background:#0d6efd;"></span>
                                    Pick Face: {{ $pickFaceCount }}
                                </span>
                                @endif
                                @if($inactiveCount > 0)
                                <span class="flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full inline-block" style="background:#6c757d;"></span>
                                    Inactive: {{ $inactiveCount }}
                                </span>
                                @endif
                                <span class="text-gray-400">Total: {{ $totalBins }}</span>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button wire:click="openEditRack('{{ $activeZoneTab . '|' . $rackName }}')"
                                    class="px-3 py-1.5 text-xs font-medium rounded border text-blue-600 border-blue-200 hover:bg-blue-50">
                                <i class="fas fa-edit mr-1"></i> Edit Rack
                            </button>
                            <button wire:click="deleteRack('{{ $activeZoneTab . '|' . $rackName }}')"
                                    wire:confirm="Delete rack {{ $activeZoneTab }}-{{ $rackName }} and all its {{ $totalBins }} bins?"
                                    class="px-3 py-1.5 text-xs font-medium rounded border text-red-600 border-red-200 hover:bg-red-50">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>
                        </div>
                    </div>

                    {{-- Bin Grid --}}
                    <div class="p-4 overflow-x-auto w-full">
                        {{-- Column headers (aisles) --}}
                        @php $aisles = collect($bins)->pluck('aisle')->unique()->sort()->values(); @endphp
                        <div class="overflow-x-auto pb-4">
                            <table class="border-collapse border-spacing-2" style="border-spacing: 0.5rem; border-collapse: separate;">
                                <thead>
                                    <tr>
                                        <th class="w-12"></th>
                                        @foreach($aisles as $aisle)
                                        <th class="p-0 text-center text-xs font-bold text-gray-400 shrink-0 w-[70px]">Col {{ $aisle }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($byShelf as $shelf => $shelvedBins)
                                    <tr>
                                        <td class="p-0 text-xs font-bold text-gray-500 text-right pr-2 w-12 align-middle">{{ $shelf }}</td>
                                        @foreach($aisles as $aisle)
                                        @php $bin = $shelvedBins[$aisle] ?? null; @endphp
                                        <td class="p-0 w-[70px]">
                                            @if($bin)
                                                @php
                                                    $status = $bin->bin_status ?? 'ACTIVE';
                                                    $stockEntries = \App\Models\BinProductStock::with('product')->where('warehouse_bin_id', $bin->id)->where('quantity', '>', 0)->get();
                                                    $stockQty = $stockEntries->sum('quantity');
                                                    $statusColors = [
                                                        'ACTIVE'    => ['bg'=>'#d1e7dd','border'=>'#198754','text'=>'#0a3622'],
                                                        'INACTIVE'  => ['bg'=>'#e9ecef','border'=>'#6c757d','text'=>'#6c757d'],
                                                        'PICK_FACE' => ['bg'=>'#cfe2ff','border'=>'#0d6efd','text'=>'#084298'],
                                                    ];
                                                    $colors = $statusColors[$status] ?? $statusColors['ACTIVE'];
                                                @endphp
                                                <button wire:click="openBinStatus({{ $bin->id }})"
                                                        title="{{ $bin->bin_code }} · {{ $status }}{{ $stockQty > 0 ? ' · Qty: '.$stockQty : '' }}"
                                                        class="rounded border text-center py-2 px-1 text-xs font-bold transition hover:opacity-80 cursor-pointer flex flex-col items-center justify-center w-full mx-auto"
                                                        style="background:{{ $colors['bg'] }};border-color:{{ $colors['border'] }};color:{{ $colors['text'] }};height:60px;">
                                                    <div class="font-mono text-[10px] sm:text-xs leading-tight truncate">{{ $shelf }}-{{ $aisle }}</div>
                                                    @if($status === 'PICK_FACE')
                                                        <div class="text-xs mt-0.5"><i class="fas fa-hand-point-down"></i></div>
                                                    @elseif($status === 'INACTIVE')
                                                        <div class="text-xs mt-0.5"><i class="fas fa-lock"></i></div>
                                                    @endif
                                                </button>
                                            @else
                                                <div class="rounded border border-dashed text-center flex items-center justify-center py-2 text-xs text-gray-300 w-full mx-auto"
                                                     style="border-color:#dee2e6;height:60px;">—</div>
                                            @endif
                                        </td>
                                        @endforeach
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Legend --}}
                        <div class="flex gap-4 mt-3 text-xs text-gray-500 border-t pt-2" style="border-color:#dee2e6;">
                            <span class="flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded inline-block border" style="background:#d1e7dd;border-color:#198754;"></span>
                                Active
                            </span>
                            <span class="flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded inline-block border" style="background:#cfe2ff;border-color:#0d6efd;"></span>
                                Pick Face
                            </span>
                            <span class="flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded inline-block border" style="background:#e9ecef;border-color:#6c757d;"></span>
                                Inactive
                            </span>
                            <span class="ml-auto text-gray-400">Click any bin to change status</span>
                        </div>
                    </div>
                </div>
                @endforeach

            @else
                <div class="text-center py-16 text-gray-400">
                    <i class="fas fa-warehouse text-5xl mb-4 text-indigo-200"></i>
                    <p class="text-lg font-medium">Select a warehouse to manage its rack configuration</p>
                    <p class="text-sm">Choose from the dropdown above or create a new warehouse.</p>
                </div>
            @endif

        
            @endif

            
            {{-- ════════════════ TAB: ZONES ════════════════ --}}
            @if($activeTab === 'zones')
            <div class="p-6">

            <div class="flex justify-between items-center mb-4">
                <h3 class="font-semibold text-gray-700">Warehouse Zones</h3>
                <button wire:click="openAddZone"
                        class="flex items-center gap-2 px-4 py-2 text-sm text-white rounded font-medium"
                        style="background:#0d6efd;">
                    <i class="fas fa-plus text-xs"></i> Add Zone
                </button>
            </div>

            <div class="rounded border overflow-hidden" style="border-color:#dee2e6;">
                <table class="min-w-full divide-y text-sm" style="border-color:#dee2e6;">
                    <thead style="background:#f8f9fa;">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">S/N</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Warehouse Zone</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Racks</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Bins</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y bg-white" style="border-color:#dee2e6;">
                        @forelse($zoneTableData as $i => $zone)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-500">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 font-medium">
                                <span class="px-2 py-0.5 rounded text-sm font-bold"
                                      style="background:#e9d8fd;color:#6b21a8;">
                                    Zone {{ $zone['name'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $zone['racks'] }}</td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $zone['bins'] }}</td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex justify-center gap-2">
                                    <button wire:click="openEditZone('{{ $zone['name'] }}')"
                                            class="px-2 py-1 text-xs font-semibold text-blue-600 hover:bg-blue-50 border border-blue-200 rounded" title="Edit">
                                        Edit
                                    </button>
                                    <button wire:click="deleteZone('{{ $zone['name'] }}')"
                                            wire:confirm="Delete zone '{{ $zone['name'] }}'?"
                                            class="px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50 border border-red-200 rounded" title="Delete">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center py-12 text-gray-400">
                            <p class="text-lg">No zones configured yet.</p>
                            <p class="text-sm">Zones are created automatically when you add racks via the Bin Maintenance tab.</p>
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        
            </div>
            @endif

            {{-- ════════════════ TAB: BIN STOCK DETAIL ════════════════ --}}
            @if($activeTab === 'bin_stock')
            <div class="p-6">
                {{-- Filters --}}
                <div class="flex flex-wrap gap-3 mb-5">
                    <select wire:model.live="filterZone" class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">All Zones</option>
                        @foreach($zones as $z)
                            <option value="{{ $z }}">Zone {{ $z }}</option>
                        @endforeach
                    </select>
                    <input wire:model.live="filterRack" placeholder="Filter by Rack (e.g. R1)"
                           class="text-sm border border-gray-300 rounded-lg px-3 py-2 w-44 focus:ring-indigo-500 focus:border-indigo-500">
                    <select wire:model.live="filterBinType" class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">All Types</option>
                        <option>standard</option><option>bulk</option><option>cold</option><option>hazmat</option>
                    </select>
                    <button wire:click="clearFilters"
                            class="text-xs text-gray-500 hover:text-red-600 underline">Clear</button>
                </div>

                {{-- Bin Table --}}
                <div class="overflow-x-auto border rounded-xl" style="border-color:#dee2e6;">
                    <table class="min-w-full divide-y text-sm bg-white" style="border-color:#dee2e6;">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Bin / Location</th>
                                <th class="px-4 py-3 text-left">Status & Type</th>
                                <th class="px-4 py-3 text-left">Products Stored</th>
                                <th class="px-4 py-3 text-right">Total Units</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($pageBins as $bin)
                                @php
                                    $binTotal = $bin->stockEntries->sum('quantity');
                                    $typeColors = ['standard'=>'blue','bulk'=>'amber','cold'=>'cyan','hazmat'=>'red'];
                                    $tc = $typeColors[$bin->bin_type] ?? 'gray';
                                @endphp
                                <tr class="hover:bg-gray-50 {{ !$bin->is_active ? 'opacity-60' : '' }}">
                                    <td class="px-4 py-4 align-top">
                                        <div class="font-mono font-bold text-gray-700 text-base mb-1">{{ $bin->bin_code }}</div>
                                        @if($bin->zone)
                                            <div class="text-xs text-gray-500 font-medium">
                                                Zone {{ $bin->zone }} › {{ $bin->rack }} › {{ $bin->shelf }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex flex-col items-start gap-1">
                                            <span class="text-xs px-2 py-0.5 rounded-full font-semibold border
                                                         {{ $tc === 'blue' ? 'bg-blue-50 text-blue-700 border-blue-200' : '' }}
                                                         {{ $tc === 'amber' ? 'bg-amber-50 text-amber-700 border-amber-200' : '' }}
                                                         {{ $tc === 'cyan' ? 'bg-cyan-50 text-cyan-700 border-cyan-200' : '' }}
                                                         {{ $tc === 'red' ? 'bg-red-50 text-red-700 border-red-200' : '' }}">
                                                {{ ucfirst($bin->bin_type) }}
                                            </span>
                                            @if(!$bin->is_active)
                                                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 border border-gray-200 font-medium">Inactive</span>
                                            @else
                                                <span class="text-xs px-2 py-0.5 rounded-full bg-green-50 text-green-700 border border-green-200 font-medium">Active</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        @if($bin->stockEntries->count() > 0)
                                            <div class="space-y-2">
                                                @foreach($bin->stockEntries->sortByDesc('quantity') as $entry)
                                                    <div class="flex items-center gap-2 border-b border-gray-50 pb-2 last:border-0 last:pb-0">
                                                        <div class="w-6 h-6 rounded bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold text-xs shrink-0">
                                                            {{ substr($entry->product->name ?? '?', 0, 1) }}
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <div class="font-medium text-gray-800 text-xs truncate" title="{{ $entry->product->name ?? 'Unknown' }}">
                                                                {{ $entry->product->name ?? 'Unknown' }}
                                                            </div>
                                                            <div class="text-gray-400 text-[10px]">
                                                                SKU: {{ $entry->product->sku ?? '-' }}
                                                            </div>
                                                        </div>
                                                        <div class="text-right whitespace-nowrap">
                                                            @if($entry->product && $entry->quantity < ($entry->product->reorder_level ?? 10))
                                                                <span class="text-[10px] text-red-500 font-bold mr-1" title="Low Stock">⚠</span>
                                                            @endif
                                                            <span class="font-bold text-gray-800">{{ number_format($entry->quantity) }}</span>
                                                            <span class="text-[10px] text-gray-500">{{ $entry->product->unit_of_measure ?? 'pcs' }}</span>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-sm text-gray-400 italic">Empty bin</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top text-right">
                                        <div class="text-lg font-bold text-gray-800">{{ number_format($binTotal) }}</div>
                                        <div class="text-xs text-gray-400">{{ $bin->stockEntries->count() }} SKUs</div>
                                    </td>
                                    <td class="px-4 py-4 align-top text-right">
                                        <div class="flex flex-col gap-1 items-end">
                                            <button wire:click="openStockModal({{ $bin->id }})"
                                                    class="w-24 text-xs py-1 text-center bg-green-50 text-green-700 border border-green-200 rounded hover:bg-green-100 transition font-medium">
                                                <i class="fas fa-plus mr-1"></i> Stock
                                            </button>
                                            <button wire:click="openTransferModal({{ $bin->id }})"
                                                    class="w-24 text-xs py-1 text-center bg-amber-50 text-amber-700 border border-amber-200 rounded hover:bg-amber-100 transition font-medium">
                                                <i class="fas fa-exchange-alt mr-1"></i> Transfer
                                            </button>
                                            <div class="flex gap-1 mt-1 w-24 justify-end">
                                                <button wire:click="openBinModal({{ $bin->id }})" title="Edit Bin"
                                                        class="p-1 text-blue-600 hover:bg-blue-50 rounded">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button wire:click="toggleBinActive({{ $bin->id }})" title="{{ $bin->is_active ? 'Disable' : 'Enable' }}"
                                                        class="p-1 {{ $bin->is_active ? 'text-gray-500 hover:bg-gray-100' : 'text-yellow-600 hover:bg-yellow-50' }} rounded">
                                                    <i class="fas {{ $bin->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                                </button>
                                                <button wire:click="deleteBin({{ $bin->id }})" wire:confirm="Delete this bin?" title="Delete Bin"
                                                        class="p-1 text-red-600 hover:bg-red-50 rounded">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-12 text-gray-400">
                                        <i class="fas fa-inbox text-3xl mb-3 opacity-30"></i>
                                        <p>No bins found for this warehouse.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $pageBins->links() }}</div>
            </div>
            @endif

            {{-- ════════════════ TAB: PRODUCT LOCATOR ════════════════ --}}
            @if($activeTab === 'product_locator')
            <div class="p-6">
                <div class="mb-5">
                    <label class="text-sm font-medium text-gray-700 mb-1 block">Search Product (by name or SKU)</label>
                    <input wire:model.live.debounce.400ms="searchProduct"
                           placeholder="Type at least 2 characters…"
                           class="w-full sm:w-96 text-sm border border-gray-300 rounded-xl px-4 py-2.5 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                </div>

                @if(strlen($searchProduct) >= 2)
                    @forelse($productLocatorResults as $entry)
                        <div class="flex items-center justify-between border rounded-xl px-5 py-3 mb-3 bg-white hover:shadow-sm transition">
                            <div>
                                <p class="font-semibold text-gray-800">{{ $entry->product->name }}</p>
                                <p class="text-xs text-gray-400">SKU: {{ $entry->product->sku }}</p>
                                <div class="flex items-center gap-1 mt-1 text-xs text-indigo-700">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    {{ $entry->bin->full_label }}
                                    @if($entry->bin->warehouse)
                                        <span class="text-gray-400">· {{ $entry->bin->warehouse->name }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-bold text-gray-800">{{ number_format($entry->quantity) }}</p>
                                <p class="text-xs text-gray-400">{{ $entry->product->unit_of_measure }}</p>
                                @if($entry->unit_cost)
                                    <p class="text-xs text-green-600 font-medium">₹{{ number_format($entry->unit_cost * $entry->quantity, 0) }}</p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12 text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-2 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <p>No results for "{{ $searchProduct }}"</p>
                        </div>
                    @endforelse
                @else
                    <div class="text-center py-12 text-gray-300">
                        <svg class="w-16 h-16 mx-auto mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <p class="text-gray-400">Type to search for any product across this warehouse</p>
                    </div>
                @endif
            </div>
            @endif

            {{-- ════════════════ TAB: TRANSFERS ════════════════ --}}
            @if($activeTab === 'transfers')
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Reference</th>
                                <th class="px-4 py-3 text-left">Product</th>
                                <th class="px-4 py-3 text-left">From → To</th>
                                <th class="px-4 py-3 text-center">Qty</th>
                                <th class="px-4 py-3 text-left">Reason</th>
                                <th class="px-4 py-3 text-left">By</th>
                                <th class="px-4 py-3 text-left">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse(\App\Models\BinTransfer::with(['product','fromBin','toBin','transferrer'])
                                ->whereHas('fromBin', fn($q) => $q->where('warehouse_id', $selectedWarehouseId))
                                ->latest()->limit(50)->get() as $tx)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-mono text-xs text-indigo-600">{{ $tx->reference_no }}</td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium">{{ $tx->product->name ?? '-' }}</p>
                                        <p class="text-xs text-gray-400">{{ $tx->product->sku ?? '' }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-gray-600">{{ $tx->fromBin->full_label ?? '-' }}</span>
                                        <span class="text-gray-400 mx-1">→</span>
                                        <span class="text-indigo-700">{{ $tx->toBin->full_label ?? '-' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center font-bold">{{ $tx->quantity }}</td>
                                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $tx->reason ?? '-' }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-500">{{ $tx->transferrer->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-400">{{ $tx->created_at->format('d M Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center py-12 text-gray-400">No transfers yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

        </div>
        @endif

    </div>

    {{-- ═══════════ MODAL: Add/Edit Bin ═══════════ --}}
    @if($showBinModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-data x-on:keydown.escape.window="$wire.showBinModal = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h2 class="text-lg font-bold text-gray-800">{{ $editingBinId ? 'Edit Bin' : 'Add New Bin' }}</h2>
                <button wire:click="$set('showBinModal', false)" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Bin Code *</label>
                        <input wire:model="bin_code" placeholder="e.g. A01-R1-S1" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @error('bin_code') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Bin Type</label>
                        <select wire:model="bin_type" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option>standard</option><option>bulk</option><option>cold</option><option>hazmat</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-4 gap-3">
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Zone</label>
                        <input wire:model="bin_zone" placeholder="A" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Aisle</label>
                        <input wire:model="bin_aisle" placeholder="01" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Rack</label>
                        <input wire:model="bin_rack" placeholder="R1" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Shelf</label>
                        <input wire:model="bin_shelf" placeholder="S1" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Max Weight (kg)</label>
                    <input wire:model="bin_max_weight" type="number" placeholder="500" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Notes</label>
                    <textarea wire:model="bin_notes" rows="2" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" placeholder="Optional notes about this bin…"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50">
                <button wire:click="$set('showBinModal', false)" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100">Cancel</button>
                <button wire:click="saveBin" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">
                    {{ $editingBinId ? 'Update Bin' : 'Create Bin' }}
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════ MODAL: Stock Adjustment ═══════════ --}}
    @if($showStockModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h2 class="text-lg font-bold text-gray-800">Adjust Stock</h2>
                <button wire:click="$set('showStockModal', false)" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Product *</label>
                    <select wire:model="stockProductId" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-indigo-500">
                        <option value="">Select product…</option>
                        @foreach($allProducts as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                        @endforeach
                    </select>
                    @error('stockProductId') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Quantity (use negative to deduct) *</label>
                    <input wire:model="stockQty" type="number" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    @error('stockQty') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Note</label>
                    <input wire:model="stockNote" placeholder="Reason for adjustment" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50">
                <button wire:click="$set('showStockModal', false)" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100">Cancel</button>
                <button wire:click="saveStockAdjustment" class="px-5 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg">Save Adjustment</button>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════ MODAL: Bin Transfer ═══════════ --}}
    @if($showTransferModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h2 class="text-lg font-bold text-gray-800">Transfer Stock Between Bins</h2>
                <button wire:click="$set('showTransferModal', false)" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Product *</label>
                    <select wire:model="txProductId" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-indigo-500">
                        <option value="">Select product…</option>
                        @foreach($allProducts as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                        @endforeach
                    </select>
                    @error('txProductId') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">From Bin *</label>
                        <select wire:model="txFromBinId" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-indigo-500">
                            <option value="">Select…</option>
                            @foreach($allBins as $b)
                                <option value="{{ $b->id }}">{{ $b->warehouse->name ?? '' }} > {{ $b->full_label }}</option>
                            @endforeach
                        </select>
                        @error('txFromBinId') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">To Bin *</label>
                        <select wire:model="txToBinId" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-indigo-500">
                            <option value="">Select…</option>
                            @foreach($allBins as $b)
                                <option value="{{ $b->id }}">{{ $b->warehouse->name ?? '' }} > {{ $b->full_label }}</option>
                            @endforeach
                        </select>
                        @error('txToBinId') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Quantity *</label>
                    <input wire:model="txQty" type="number" min="1" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    @error('txQty') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Reason</label>
                    <input wire:model="txReason" placeholder="e.g. Rebalancing, Damaged goods relocation…" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50">
                <button wire:click="$set('showTransferModal', false)" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100">Cancel</button>
                <button wire:click="saveTransfer" class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-lg">Confirm Transfer</button>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══ MERGED MODALS ═══ --}}

@if($showRackModal)
<div class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,0.5);">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4" @click.stop>
        {{-- Modal header --}}
        <div class="flex items-center justify-between px-6 py-4 rounded-t-xl text-white"
             style="background:#6366f1;">
            <h5 class="font-bold text-lg">{{ $isEditMode ? 'Edit Rack' : 'Add New Rack' }}</h5>
            <button wire:click="$set('showRackModal',false)" class="text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="px-6 py-5 space-y-4">
            {{-- Row 1: Zone | Rack Name | Staging --}}
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Warehouse Zone *</label>
                    <select wire:model.live="ra_zone" class="w-full text-sm border rounded px-3 py-2"
                            style="border-color:#ced4da;">
                        <option value="">Select</option>
                        @foreach($zoneTabs as $z)
                            <option value="{{ $z }}">{{ $z }}</option>
                        @endforeach
                        <option value="NEW">+ New Zone (type below)</option>
                    </select>
                    @error('ra_zone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    @if($ra_zone === 'NEW')
                        <input wire:model="ra_zone_new" placeholder="Enter zone letter (A, B, C…)"
                               class="w-full text-sm border rounded px-3 py-2 mt-2" style="border-color:#ced4da;">
                        @error('ra_zone_new') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    @endif
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Rack Name *</label>
                    <input wire:model="ra_rack_name" placeholder="e.g. R1, RACK-A1"
                           class="w-full text-sm border rounded px-3 py-2" style="border-color:#ced4da;">
                    @error('ra_rack_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="flex items-center gap-2 pt-5">
                    <input type="checkbox" wire:model.live="isStagingRack" id="staging_cb" class="rounded">
                    <label for="staging_cb" class="text-sm font-medium text-gray-700 cursor-pointer">Set as Staging</label>
                </div>
            </div>

            @if($isStagingRack)
                <div class="text-xs text-amber-600 bg-amber-50 rounded px-3 py-2">
                    ℹ Staging rack uses fixed 1×1 dimensions (single bin)
                </div>
            @endif

            {{-- Row 2: Capacity (auto) | Bin Type --}}
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Capacity (auto)</label>
                    <input type="number" value="{{ $ra_capacity }}" disabled
                           class="w-full text-sm border rounded px-3 py-2 bg-gray-50" style="border-color:#ced4da;">
                    <p class="text-xs text-gray-400 mt-0.5">= Rows × Columns</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Bin Type</label>
                    <select wire:model="ra_bin_type" {{ $isStagingRack ? 'disabled' : '' }}
                            class="w-full text-sm border rounded px-3 py-2" style="border-color:#ced4da;">
                        <option value="standard">Standard</option>
                        <option value="bulk">Bulk</option>
                        <option value="cold">Cold Storage</option>
                        <option value="hazmat">Hazmat</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Max Weight (kg)</label>
                    <input type="number" wire:model="ra_max_weight" min="0"
                           class="w-full text-sm border rounded px-3 py-2" style="border-color:#ced4da;">
                </div>
            </div>

            {{-- Row 3: Height / Rows --}}
            <div>
                <label class="text-xs font-medium text-gray-600 mb-2 block">
                    Rack Height (Rows / Shelves)
                    <span class="text-gray-400 ml-1">— vertical levels, S1 = bottom</span>
                </label>
                <div class="flex items-center gap-3">
                    <div class="flex-1">
                        <label class="text-xs text-gray-400">Start</label>
                        <input type="number" wire:model.live="ra_height_start" min="1" {{ $isStagingRack ? 'disabled' : '' }}
                               class="w-full text-sm border rounded px-3 py-2 mt-0.5" style="border-color:#ced4da;">
                    </div>
                    <span class="text-gray-400 pt-4">—</span>
                    <div class="flex-1">
                        <label class="text-xs text-gray-400">End</label>
                        <input type="number" wire:model.live="ra_height_end" min="1" {{ $isStagingRack ? 'disabled' : '' }}
                               class="w-full text-sm border rounded px-3 py-2 mt-0.5" style="border-color:#ced4da;">
                    </div>
                    <div class="flex-1 text-center pt-4">
                        <span class="text-sm font-bold text-indigo-600">
                            {{ max(0, (int)$ra_height_end - (int)$ra_height_start + 1) }} rows
                        </span>
                    </div>
                </div>
            </div>

            {{-- Row 4: Depth / Columns --}}
            <div>
                <label class="text-xs font-medium text-gray-600 mb-2 block">
                    Range / Depth (Columns / Aisles)
                    <span class="text-gray-400 ml-1">— horizontal slots</span>
                </label>
                <div class="flex items-center gap-3">
                    <div class="flex-1">
                        <label class="text-xs text-gray-400">Start</label>
                        <input type="number" wire:model.live="ra_depth_start" min="1" {{ $isStagingRack ? 'disabled' : '' }}
                               class="w-full text-sm border rounded px-3 py-2 mt-0.5" style="border-color:#ced4da;">
                    </div>
                    <span class="text-gray-400 pt-4">—</span>
                    <div class="flex-1">
                        <label class="text-xs text-gray-400">End</label>
                        <input type="number" wire:model.live="ra_depth_end" min="1" {{ $isStagingRack ? 'disabled' : '' }}
                               class="w-full text-sm border rounded px-3 py-2 mt-0.5" style="border-color:#ced4da;">
                    </div>
                    <div class="flex-1 text-center pt-4">
                        <span class="text-sm font-bold text-indigo-600">
                            {{ max(0, (int)$ra_depth_end - (int)$ra_depth_start + 1) }} cols
                        </span>
                    </div>
                </div>
            </div>

            {{-- Bin Status Preview --}}
            <div class="rounded border px-4 py-3" style="border-color:#dee2e6;background:#f8f9fa;">
                <p class="text-xs font-medium text-gray-600 mb-2">Bin Status (default when adding):</p>
                <div class="flex gap-3 text-xs">
                    <span class="px-3 py-1.5 rounded-full font-medium" style="background:#d1e7dd;color:#0a3622;">● Active <em>(default)</em></span>
                    <span class="px-3 py-1.5 rounded-full font-medium text-gray-500" style="background:#e9ecef;">● Mark as Inactive</span>
                    <span class="px-3 py-1.5 rounded-full font-medium" style="background:#cfe2ff;color:#084298;">● Mark as Pick Face</span>
                </div>
                <p class="text-xs text-gray-400 mt-2">Click any bin in the grid to change its individual status after creation.</p>
            </div>

            {{-- Preview --}}
            <div class="rounded border px-4 py-3 text-xs" style="border-color:#c7d2fe;background:#eef2ff;">
                <p class="font-medium text-indigo-700 mb-1">📦 Preview: Bin codes will be generated as:</p>
                <code class="text-indigo-800">
                    {{ $ra_zone ?: 'ZONE' }}-{{ $ra_rack_name ?: 'RACK' }}-S1-01 …
                    {{ $ra_zone ?: 'ZONE' }}-{{ $ra_rack_name ?: 'RACK' }}-S{{ max(1, (int)$ra_height_end - (int)$ra_height_start + 1) }}-{{ str_pad(max(1, (int)$ra_depth_end - (int)$ra_depth_start + 1), 2, '0', STR_PAD_LEFT) }}
                </code>
                <p class="text-indigo-500 mt-1">Total: <strong>{{ $ra_capacity }} bins</strong></p>
            </div>
        </div>

        <div class="flex justify-end gap-3 px-6 py-4 border-t rounded-b-xl" style="background:#f8f9fa;border-color:#dee2e6;">
            <button wire:click="$set('showRackModal',false)"
                    class="px-4 py-2 text-sm border rounded-lg text-gray-600 hover:bg-gray-100"
                    style="border-color:#ced4da;">Cancel</button>
            <button wire:click="saveRack"
                    class="px-5 py-2 text-sm text-white rounded-lg font-medium"
                    style="background:#6366f1;">
                {{ $isEditMode ? 'Update Rack' : 'Create Rack' }}
            </button>
        </div>
    </div>
</div>
@endif

{{-- ═══ MODAL: Bin Status & Products ═══ --}}
@if($showBinStatusModal)
<div class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,0.5);">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl mx-4 flex flex-col max-h-[90vh]" @click.stop>
        <div class="flex items-center justify-between px-6 py-4 rounded-t-xl text-white shrink-0"
             style="background:#6366f1;">
            <h5 class="font-bold">Bin Details</h5>
            <button wire:click="$set('showBinStatusModal',false)" class="text-white"><i class="fas fa-times"></i></button>
        </div>
        
        <div class="flex flex-col md:flex-row overflow-hidden">
            <!-- Left Column: Status Update -->
            <div class="w-full md:w-1/3 p-6 border-b md:border-b-0 md:border-r border-gray-200 overflow-y-auto">
                <h6 class="text-sm font-bold text-gray-700 mb-3">Update Bin Status</h6>
                <div class="space-y-3">
                    @foreach([
                        ['ACTIVE',    'Active',    '#d1e7dd','#0a3622', 'fas fa-check-circle'],
                        ['INACTIVE',  'Inactive',  '#e9ecef','#6c757d', 'fas fa-lock'],
                        ['PICK_FACE', 'Pick Face', '#cfe2ff','#084298', 'fas fa-hand-point-down'],
                    ] as [$val,$label,$bg,$color,$icon])
                    <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition hover:shadow-sm"
                           style="{{ $binNewStatus === $val ? "background:{$bg};border-color:{$color};" : "border-color:#dee2e6;" }}">
                        <input type="radio" wire:model.live="binNewStatus" value="{{ $val }}" class="hidden">
                        <i class="{{ $icon }} text-sm" style="color:{{ $color }};"></i>
                        <span class="font-medium text-sm" style="color:{{ $binNewStatus === $val ? $color : '#374151' }};">
                            {{ $label }}
                        </span>
                        @if($binNewStatus === $val)
                            <i class="fas fa-check ml-auto text-sm" style="color:{{ $color }};"></i>
                        @endif
                    </label>
                    @endforeach
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <button wire:click="saveBinStatus" class="w-full px-4 py-2 text-sm text-white rounded font-medium" style="background:#6366f1;">
                        Save Status
                    </button>
                </div>
            </div>

            <!-- Right Column: Products & Add -->
            <div class="w-full md:w-2/3 p-6 flex flex-col overflow-hidden">
                @if (session()->has('bin_success'))
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-3 py-2 rounded text-sm relative">
                        {{ session('bin_success') }}
                    </div>
                @endif

                <h6 class="text-sm font-bold text-gray-700 mb-3">Products in this Bin</h6>
                <div class="flex-1 overflow-y-auto min-h-[150px] bg-gray-50 rounded-lg border border-gray-200 p-2 mb-4">
                    @if(count($binStatusProducts) > 0)
                        <div class="space-y-2">
                            @foreach($binStatusProducts as $prod)
                                <div class="bg-white p-3 rounded border border-gray-200 text-sm flex justify-between items-center shadow-sm">
                                    <div>
                                        <div class="font-semibold text-gray-800">{{ $prod['name'] }}</div>
                                        <div class="text-xs text-gray-500">SKU: {{ $prod['sku'] }}</div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="font-bold text-indigo-700 text-lg">
                                            {{ number_format($prod['qty']) }} <span class="text-xs text-gray-500 font-normal">{{ $prod['uom'] }}</span>
                                        </div>
                                        <button wire:click="openTransferModal({{ $editingBinDbId }}, {{ $prod['product_id'] }})" class="p-2 text-blue-600 hover:bg-blue-50 rounded" title="Transfer Stock">
                                            <i class="fas fa-exchange-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center text-gray-400 py-6 text-sm">
                            <i class="fas fa-box-open text-3xl mb-2 opacity-50"></i>
                            <p>Bin is currently empty</p>
                        </div>
                    @endif
                </div>

                <!-- Add Product Form -->
                <div class="bg-indigo-50 rounded-lg p-4 border border-indigo-100 shrink-0">
                    <h6 class="text-sm font-bold text-indigo-900 mb-3"><i class="fas fa-plus-circle mr-1"></i> Add Product to Bin</h6>
                    <form wire:submit.prevent="addProductToBin" class="flex gap-3 items-end">
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-indigo-700 mb-1">Product</label>
                            <select wire:model="binAddProductId" class="w-full text-sm rounded border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select a product...</option>
                                @foreach($allProductsList as $p)
                                    <option value="{{ $p['id'] }}">{{ $p['name'] }} ({{ $p['sku'] }})</option>
                                @endforeach
                            </select>
                            @error('binAddProductId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="w-24">
                            <label class="block text-xs font-medium text-indigo-700 mb-1">Quantity</label>
                            <input type="number" wire:model="binAddProductQty" min="1" class="w-full text-sm rounded border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('binAddProductQty') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <button type="submit" class="px-4 py-2 text-sm text-white rounded font-medium bg-indigo-600 hover:bg-indigo-700 transition h-[38px]">
                            Add
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="flex justify-end px-6 py-4 border-t rounded-b-xl shrink-0" style="background:#f8f9fa;border-color:#dee2e6;">
            <button wire:click="$set('showBinStatusModal',false)"
                    class="px-5 py-2 text-sm border rounded text-gray-600 font-medium bg-white hover:bg-gray-50" style="border-color:#ced4da;">Close</button>
        </div>
    </div>
</div>
@endif

{{-- ═══ MODAL: Add/Edit Zone ═══ --}}
@if($showZoneModal)
<div class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,0.5);">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm mx-4" @click.stop>
        <div class="flex items-center justify-between px-6 py-4 rounded-t-xl text-white"
             style="background:#6366f1;">
            <h5 class="font-bold">{{ $editingZoneId ? 'Edit' : 'Add' }} Warehouse Zone</h5>
            <button wire:click="$set('showZoneModal',false)" class="text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="px-6 py-5">
            <label class="text-xs font-medium text-gray-600 mb-1 block">Zone Name / Letter *</label>
            <input wire:model="zone_name" placeholder="e.g. A, B, ZONE-C"
                   class="w-full text-sm border rounded px-3 py-2" style="border-color:#ced4da;">
            <p class="text-xs text-gray-400 mt-1">Use single letters (A, B, C) or short names (RAW, FG, COLD)</p>
        </div>
        <div class="flex justify-end gap-3 px-6 py-4 border-t rounded-b-xl" style="background:#f8f9fa;border-color:#dee2e6;">
            <button wire:click="$set('showZoneModal',false)"
                    class="px-4 py-2 text-sm border rounded text-gray-600" style="border-color:#ced4da;">Cancel</button>
            <button wire:click="saveZone"
                    class="px-5 py-2 text-sm text-white rounded font-medium" style="background:#6366f1;">Save Zone</button>
        </div>
    </div>
</div>
@endif

{{-- ═══ MODAL: Edit Warehouse ═══ --}}
@if($showWarehouseModal)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-data x-on:keydown.escape.window="$wire.showWarehouseModal = false">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg" @click.stop>
        <div class="flex items-center justify-between px-6 py-4 border-b">
            <h2 class="text-lg font-bold text-gray-800">Edit Warehouse Details</h2>
            <button wire:click="$set('showWarehouseModal', false)" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <div class="px-6 py-5 space-y-4">
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Warehouse Name *</label>
                <input wire:model="wh_name" placeholder="e.g. Main Distribution Center" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('wh_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Location Code / City</label>
                <input wire:model="wh_location" placeholder="e.g. NYC-01" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
            </div>
            
            <div class="border-t border-gray-100 pt-3">
                <h4 class="text-xs font-bold text-indigo-700 uppercase mb-3">Contact Information</h4>
                <div class="grid grid-cols-2 gap-4 mb-3">
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Contact Person</label>
                        <input wire:model="wh_contact_name" placeholder="e.g. John Doe" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Phone Number</label>
                        <input wire:model="wh_contact_phone" placeholder="e.g. +1 234 567 8900" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Email Address</label>
                    <input wire:model="wh_contact_email" type="email" placeholder="e.g. warehouse@example.com" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div class="mt-3">
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Physical Address</label>
                    <textarea wire:model="wh_address" rows="2" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" placeholder="Full street address..."></textarea>
                </div>
            </div>
        </div>
        <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50 rounded-b-2xl">
            <button wire:click="$set('showWarehouseModal', false)" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100">Cancel</button>
            <button wire:click="saveWarehouse" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">
                Save Details
            </button>
        </div>
    </div>
</div>
@endif

</div>