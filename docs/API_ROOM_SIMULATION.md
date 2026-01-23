# Room Layout Generation Algorithms & Architecture Documentation

## Table of Contents

1. [System Overview](#system-overview)
2. [Architecture Diagram](#architecture-diagram)
3. [Layout Generation Flow](#layout-generation-flow)
4. [Validation Flow](#validation-flow)
5. [Grid Calculation Algorithms](#grid-calculation-algorithms)
6. [Packing Strategies](#packing-strategies)
7. [Database Schema (ERD)](#database-schema-erd)
8. [Component Interactions](#component-interactions)
9. [Fallback Mechanisms](#fallback-mechanisms)
10. [Algorithm Selection Logic](#algorithm-selection-logic)

---

## System Overview

The Room Layout Generation System is an intelligent warehouse management solution that automatically calculates optimal product placement within storage rooms. The system uses advanced algorithms to maximize space utilization while ensuring products fit correctly and stock availability is validated.

### Key Features

- **Smart Grid Calculation**: Dimension-based grid optimization
- **Multiple Packing Strategies**: Compartment, LAFF, and Hybrid approaches
- **Pre-Generation Validation**: Room and stock validation before layout creation
- **Real-time Feedback**: Live validation and preview in UI
- **Adaptive Algorithms**: Automatic strategy selection based on product mix

---

## Architecture Diagram

```mermaid
graph TB
    subgraph Frontend["Web Dashboard"]
        UI[User Interface]
        LG[LayoutGenerator Component]
        VP[ValidationPanel]
        PP[PreviewPanel]
        RD[RoomDetails Page]
    end
    
    subgraph API["API Layer"]
        RLC[RoomLayoutController]
        RC[RoomController]
        VAL[Validation Endpoints]
    end
    
    subgraph Validation["Validation Services"]
        RVS[RoomValidationService]
        SVS[StockValidationService]
        LV[LayoutValidator]
    end
    
    subgraph Packing["Packing Services"]
        SGC[SmartGridCalculator]
        CM[CompartmentManager]
        CPS[CompartmentPackingService]
        LPS[LAFFPackingService]
        HPS[HybridPackingService]
        PGS[ProductGroupingService]
        PSS[PlacementStrategyService]
    end
    
    subgraph Spatial["Spatial Services"]
        CD[CollisionDetector]
        FSM[FreeSpaceManager]
    end
    
    subgraph Optimization["Optimization"]
        LOS[LayoutOptimizationService]
    end
    
    subgraph Database["Database"]
        DB[(MySQL Database)]
        RM[Room Model]
        PR[Product Model]
        PD[ProductDimension Model]
        WS[WarehouseStock Model]
        RS[RoomStock Model]
        LP[Layout Model]
        IP[ItemPlacement Model]
    end
    
    UI --> LG
    UI --> VP
    UI --> PP
    UI --> RD
    
    LG --> RLC
    VP --> VAL
    PP --> VAL
    RD --> RC
    
    RLC --> RVS
    RLC --> SVS
    RLC --> LV
    RLC --> CPS
    RLC --> LPS
    RLC --> HPS
    
    VAL --> RVS
    VAL --> SVS
    
    CPS --> CM
    CPS --> SGC
    CPS --> PGS
    CPS --> PSS
    CPS --> CD
    
    LPS --> PSS
    LPS --> FSM
    LPS --> CD
    
    HPS --> CPS
    HPS --> LPS
    HPS --> PGS
    
    CM --> SGC
    
    PSS --> CD
    PSS --> FSM
    
    RLC --> LOS
    
    RVS --> DB
    SVS --> DB
    RLC --> DB
    RC --> DB
    
    DB --> RM
    DB --> PR
    DB --> PD
    DB --> WS
    DB --> RS
    DB --> LP
    DB --> IP
```

---

## Layout Generation Flow

```mermaid
flowchart TD
    Start([User Requests Layout Generation]) --> ValidateRequest{Validate Request}
    
    ValidateRequest -->|Invalid| ShowErrors[Display Validation Errors]
    ValidateRequest -->|Valid| FetchData[Fetch Room & Products Data]
    
    FetchData --> PreValidate[Pre-Generation Validation]
    
    PreValidate --> RoomCheck{Room Validation}
    RoomCheck -->|Fails| RoomErrors[Return Room Errors]
    RoomCheck -->|Passes| StockCheck{Stock Validation}
    
    StockCheck -->|Fails| StockErrors[Return Stock Errors]
    StockCheck -->|Passes| CalculateCapacity[Calculate Theoretical Capacity]
    
    CalculateCapacity --> SelectStrategy[Select Packing Strategy]
    
    SelectStrategy --> StrategyType{Strategy Type?}
    
    StrategyType -->|Compartment| CompartmentFlow[Compartment Packing Flow]
    StrategyType -->|LAFF| LAFFFlow[LAFF Packing Flow]
    StrategyType -->|Hybrid| HybridFlow[Hybrid Packing Flow]
    
    CompartmentFlow --> CompartmentResult[Compartment Result]
    LAFFFlow --> LAFFResult[LAFF Result]
    HybridFlow --> HybridResult[Hybrid Result]
    
    CompartmentResult --> ValidateLayout[Validate Generated Layout]
    LAFFResult --> ValidateLayout
    HybridResult --> ValidateLayout
    
    ValidateLayout --> LayoutValid{Layout Valid?}
    
    LayoutValid -->|Invalid| LayoutErrors[Return Layout Errors]
    LayoutValid -->|Valid| Optimize[Optimize Layout]
    
    Optimize --> CalculateUtilization[Calculate Utilization]
    CalculateUtilization --> SaveLayout[Save Layout to Database]
    
    SaveLayout --> CreatePlacements[Create Item Placements]
    CreatePlacements --> Success([Return Success with Layout])
    
    RoomErrors --> End([End with Errors])
    StockErrors --> End
    LayoutErrors --> End
    ShowErrors --> End
```

---

## Validation Flow

```mermaid
flowchart TD
    Start([Validation Request]) --> PrepareItems[Prepare Items with Dimensions]
    
    PrepareItems --> ValidateRoom[Validate Room Dimensions]
    ValidateRoom --> RoomValid{Room Valid?}
    
    RoomValid -->|No| RoomError[Return Room Errors]
    RoomValid -->|Yes| ValidateProducts[Validate Products Fit in Room]
    
    ValidateProducts --> ProductFit{All Products Fit?}
    
    ProductFit -->|No| ProductError[Return Product Fit Errors]
    ProductFit -->|Yes| CalculateMaxQty[Calculate Max Quantity per Product]
    
    CalculateMaxQty --> ValidateStock[Validate Stock Availability]
    
    ValidateStock --> CheckWarehouse[Check Warehouse Stock]
    CheckWarehouse --> CheckRoomStock[Check Room Stock]
    
    CheckRoomStock --> StockSufficient{Stock Sufficient?}
    
    StockSufficient -->|No| StockError[Return Stock Errors]
    StockSufficient -->|Yes| CalculateCapacity[Calculate Theoretical Capacity]
    
    CalculateCapacity --> DetermineStrategy[Determine Recommended Strategy]
    DetermineStrategy --> GenerateSuggestions[Generate Optimization Suggestions]
    
    GenerateSuggestions --> ReturnValidation[Return Validation Result]
    
    RoomError --> End([End])
    ProductError --> End
    StockError --> End
    ReturnValidation --> End
```

---

## Grid Calculation Algorithms

```mermaid
flowchart TD
    Start([Calculate Optimal Grid]) --> GetInputs[Get Items, Room Dimensions]
    
    GetInputs --> GroupProducts[Group Products by Size]
    
    GroupProducts --> TryStrategies[Try Multiple Strategies]
    
    TryStrategies --> Strategy1[Size-Based Strategy]
    TryStrategies --> Strategy2[Aspect Ratio Strategy]
    TryStrategies --> Strategy3[Density Optimized Strategy]
    
    Strategy1 --> CalcSizeBased[Calculate Grid Based on Product Sizes]
    Strategy2 --> CalcAspectRatio[Calculate Grid Based on Aspect Ratios]
    Strategy3 --> CalcDensity[Calculate Grid for Maximum Density]
    
    CalcSizeBased --> Score1[Score Grid Configuration]
    CalcAspectRatio --> Score2[Score Grid Configuration]
    CalcDensity --> Score3[Score Grid Configuration]
    
    Score1 --> CompareScores[Compare All Scores]
    Score2 --> CompareScores
    Score3 --> CompareScores
    
    CompareScores --> SelectBest[Select Best Grid Configuration]
    
    SelectBest --> ValidateGrid[Validate Grid Against Room]
    
    ValidateGrid --> GridValid{Grid Valid?}
    
    GridValid -->|No| AdjustGrid[Adjust Grid Dimensions]
    AdjustGrid --> ValidateGrid
    
    GridValid -->|Yes| GenerateCompartments[Generate Compartment Boundaries]
    
    GenerateCompartments --> ReturnGrid[Return Grid Configuration]
    
    ReturnGrid --> End([End])
```

### Grid Calculation Strategy Details

#### 1. Size-Based Strategy
- Groups products into small, medium, large categories
- Calculates minimum cell size based on largest product
- Optimizes for minimal waste per cell

#### 2. Aspect Ratio Strategy
- Matches grid cell aspect ratio to product aspect ratios
- Considers room aspect ratio
- Optimizes for visual consistency

#### 3. Density Optimized Strategy
- Maximizes space utilization
- Considers both volume and floor area
- Targets 85%+ utilization

---

## Packing Strategies

### Compartment Packing Flow

```mermaid
flowchart TD
    Start([Compartment Packing Start]) --> ExpandItems[Expand Items by Quantity]
    
    ExpandItems --> GroupByProduct[Group Items by Product ID]
    
    GroupByProduct --> CalculateGrid[Calculate Optimal Grid]
    
    CalculateGrid --> CreateCompartments[Create Compartments for Each Product]
    
    CreateCompartments --> ForEachCompartment{For Each Compartment}
    
    ForEachCompartment --> GetProductItems[Get Items for Product]
    GetProductItems --> CalculateCellSize[Calculate Adaptive Cell Size]
    
    CalculateCellSize --> CreateGrid[Create Internal Grid in Compartment]
    
    CreateGrid --> PlaceItems[Place Items in Grid]
    
    PlaceItems --> CheckHeight{Height OK?}
    
    CheckHeight -->|No| MoveToNext[Move to Next Position]
    MoveToNext --> PlaceItems
    
    CheckHeight -->|Yes| AddPlacement[Add Placement]
    
    AddPlacement --> MoreItems{More Items?}
    
    MoreItems -->|Yes| PlaceItems
    MoreItems -->|No| NextCompartment{More Compartments?}
    
    NextCompartment -->|Yes| ForEachCompartment
    NextCompartment -->|No| CalculateUtilization[Calculate Utilization]
    
    CalculateUtilization --> ReturnResult[Return Placements & Utilization]
    
    ReturnResult --> End([End])
```

### LAFF Packing Flow

```mermaid
flowchart TD
    Start([LAFF Packing Start]) --> ExpandItems[Expand Items by Quantity]
    
    ExpandItems --> PrioritizeItems[Prioritize Items by Size & Quantity]
    
    PrioritizeItems --> InitializeFSM[Initialize Free Space Manager]
    
    InitializeFSM --> ForEachItem{For Each Item}
    
    ForEachItem --> TryPlacement[Try Placement Strategies]
    
    TryPlacement --> Strategy1[Bottom-Left Fill]
    TryPlacement --> Strategy2[Best-Fit]
    TryPlacement --> Strategy3[Waste Minimization]
    TryPlacement --> Strategy4[Stability Optimization]
    
    Strategy1 --> CheckCollision1{No Collision?}
    Strategy2 --> CheckCollision2{No Collision?}
    Strategy3 --> CheckCollision3{No Collision?}
    Strategy4 --> CheckCollision4{No Collision?}
    
    CheckCollision1 -->|Yes| SelectPlacement1[Select Placement]
    CheckCollision2 -->|Yes| SelectPlacement2[Select Placement]
    CheckCollision3 -->|Yes| SelectPlacement3[Select Placement]
    CheckCollision4 -->|Yes| SelectPlacement4[Select Placement]
    
    CheckCollision1 -->|No| TryStacking1[Try Stacking]
    CheckCollision2 -->|No| TryStacking2[Try Stacking]
    CheckCollision3 -->|No| TryStacking3[Try Stacking]
    CheckCollision4 -->|No| TryStacking4[Try Stacking]
    
    SelectPlacement1 --> AddPlacement
    SelectPlacement2 --> AddPlacement
    SelectPlacement3 --> AddPlacement
    SelectPlacement4 --> AddPlacement
    
    TryStacking1 --> StackSuccess{Stack Success?}
    TryStacking2 --> StackSuccess
    TryStacking3 --> StackSuccess
    TryStacking4 --> StackSuccess
    
    StackSuccess -->|Yes| AddPlacement[Add Placement]
    StackSuccess -->|No| UnplacedItem[Mark as Unplaced]
    
    AddPlacement --> UpdateFSM[Update Free Space Manager]
    UnplacedItem --> UpdateFSM
    
    UpdateFSM --> MoreItems{More Items?}
    
    MoreItems -->|Yes| ForEachItem
    MoreItems -->|No| CalculateUtilization[Calculate Utilization]
    
    CalculateUtilization --> ReturnResult[Return Result]
    
    ReturnResult --> End([End])
```

### Hybrid Packing Flow

```mermaid
flowchart TD
    Start([Hybrid Packing Start]) --> AnalyzeMix[Analyze Product Mix]
    
    AnalyzeMix --> DetermineStrategy{Determine Strategy}
    
    DetermineStrategy -->|Many Products, Low Qty| Compartment[Use Compartment]
    DetermineStrategy -->|Few Products, High Qty| LAFF[Use LAFF]
    DetermineStrategy -->|Mixed| Hybrid[Use Hybrid]
    
    Compartment --> RunCompartment[Run Compartment Packing]
    LAFF --> RunLAFF[Run LAFF Packing]
    Hybrid --> GroupProducts[Group Products]
    
    GroupProducts --> AllocateCompartments[Allocate Compartments]
    AllocateCompartments --> PackInCompartments[Pack Each Compartment with LAFF]
    
    PackInCompartments --> MergeResults[Merge Results]
    
    RunCompartment --> Result1[Compartment Result]
    RunLAFF --> Result2[LAFF Result]
    MergeResults --> Result3[Hybrid Result]
    
    Result1 --> CompareResults[Compare All Results]
    Result2 --> CompareResults
    Result3 --> CompareResults
    
    CompareResults --> SelectBest[Select Best Result by Utilization]
    
    SelectBest --> ReturnBest[Return Best Result]
    
    ReturnBest --> End([End])
```

---

## Database Schema (ERD)

```mermaid
erDiagram
    ROOMS ||--o{ ROOM_LAYOUTS : has
    ROOMS ||--o{ ROOM_STOCK : contains
    ROOMS ||--o{ ITEM_PLACEMENTS : contains
    ROOMS }o--|| WAREHOUSES : belongs_to
    
    PRODUCTS ||--o| PRODUCT_DIMENSIONS : has
    PRODUCTS ||--o{ WAREHOUSE_STOCK : has
    PRODUCTS ||--o{ ROOM_STOCK : has
    PRODUCTS ||--o{ ITEM_PLACEMENTS : placed_as
    PRODUCTS }o--|| CATEGORIES : belongs_to
    
    ROOM_LAYOUTS ||--o{ ITEM_PLACEMENTS : contains
    
    DRIVERS ||--o{ STOCK_ASSIGNMENTS : receives
    DRIVERS ||--o{ SALES : makes
    
    SALES ||--o{ SALE_ITEMS : contains
    SALE_ITEMS }o--|| PRODUCTS : references
    
    STOCK_ASSIGNMENTS }o--|| PRODUCTS : assigns
    STOCK_ASSIGNMENTS }o--|| ROOMS : from_room
    
    ROOMS {
        int id PK
        string name
        string description
        decimal width
        decimal depth
        decimal height
        int warehouse_id FK
        enum status
        decimal door_x
        decimal door_y
        decimal door_width
        decimal door_height
        enum door_wall
    }
    
    ROOM_LAYOUTS {
        int id PK
        int room_id FK
        string algorithm_used
        decimal utilization_percentage
        int total_items_placed
        int total_items_attempted
        json layout_data
        json compartment_config
        int grid_columns
        int grid_rows
        timestamp created_at
    }
    
    ITEM_PLACEMENTS {
        int id PK
        int room_id FK
        int product_id FK
        int layout_id FK
        decimal x_position
        decimal y_position
        decimal z_position
        decimal width
        decimal depth
        decimal height
        int stack_position
        int items_in_stack
    }
    
    PRODUCTS {
        int id PK
        string name
        decimal price
        int category_id FK
        string description
        string image_url
    }
    
    PRODUCT_DIMENSIONS {
        int id PK
        int product_id FK
        decimal width
        decimal depth
        decimal height
        decimal weight
        boolean rotatable
        boolean fragile
    }
    
    WAREHOUSE_STOCK {
        int id PK
        int product_id FK
        int quantity
        timestamp updated_at
    }
    
    ROOM_STOCK {
        int id PK
        int room_id FK
        int product_id FK
        int quantity
        timestamp updated_at
    }
    
    STOCK_ASSIGNMENTS {
        int id PK
        int driver_id FK
        int product_id FK
        int room_id FK
        int quantity
        decimal product_price_at_assignment
        enum assigned_from
        timestamp created_at
    }
    
    DRIVERS {
        int id PK
        string name
        string email
        string password
        timestamp created_at
    }
    
    WAREHOUSES {
        int id PK
        string name
        string address
    }
    
    CATEGORIES {
        int id PK
        string name
        string description
    }
```

---

## Component Interactions

```mermaid
sequenceDiagram
    participant User
    participant UI as LayoutGenerator UI
    participant API as RoomLayoutController
    participant Val as Validation Services
    participant Pack as Packing Services
    participant DB as Database
    
    User->>UI: Request Layout Generation
    UI->>API: POST /rooms/{id}/generate-layout
    
    API->>Val: Validate Room Dimensions
    Val->>DB: Fetch Room Data
    DB-->>Val: Room Data
    Val-->>API: Room Validation Result
    
    API->>Val: Validate Products Fit
    Val->>DB: Fetch Product Dimensions
    DB-->>Val: Product Dimensions
    Val-->>API: Product Validation Result
    
    API->>Val: Validate Stock Availability
    Val->>DB: Fetch Warehouse Stock
    DB-->>Val: Stock Data
    Val-->>API: Stock Validation Result
    
    alt Validation Fails
        API-->>UI: Return Validation Errors
        UI-->>User: Display Errors
    else Validation Passes
        API->>Pack: Select Packing Strategy
        Pack->>Pack: Analyze Product Mix
        
        alt Strategy: Compartment
            Pack->>Pack: Calculate Grid
            Pack->>Pack: Create Compartments
            Pack->>Pack: Place Items in Compartments
        else Strategy: LAFF
            Pack->>Pack: Prioritize Items
            Pack->>Pack: Place Items (LAFF Algorithm)
        else Strategy: Hybrid
            Pack->>Pack: Group Products
            Pack->>Pack: Allocate Compartments
            Pack->>Pack: Pack with LAFF in Each
        end
        
        Pack-->>API: Packing Result
        
        API->>Val: Validate Generated Layout
        Val-->>API: Layout Validation Result
        
        API->>Pack: Optimize Layout
        Pack-->>API: Optimized Layout
        
        API->>DB: Save Layout
        DB-->>API: Layout Saved
        
        API->>DB: Create Placements
        DB-->>API: Placements Created
        
        API-->>UI: Return Success with Layout
        UI-->>User: Display Layout
    end
```

---

## Fallback Mechanisms

```mermaid
flowchart TD
    Start([Algorithm Execution]) --> TryPrimary[Try Primary Algorithm]
    
    TryPrimary --> Success{Success?}
    
    Success -->|Yes| ReturnResult[Return Result]
    Success -->|No| CatchError[Catch Exception]
    
    CatchError --> LogError[Log Error]
    LogError --> TryFallback{Try Fallback?}
    
    TryFallback -->|Yes| Fallback1[Try Simpler Algorithm]
    TryFallback -->|No| ReturnError[Return Error]
    
    Fallback1 --> FallbackSuccess{Success?}
    
    FallbackSuccess -->|Yes| ReturnResult
    FallbackSuccess -->|No| Fallback2[Try Basic Grid Calculation]
    
    Fallback2 --> BasicSuccess{Success?}
    
    BasicSuccess -->|Yes| ReturnResult
    BasicSuccess -->|No| ReturnError
    
    ReturnResult --> End([End Successfully])
    ReturnError --> End
    
    style TryPrimary fill:#e1f5ff
    style Fallback1 fill:#fff4e1
    style Fallback2 fill:#ffe1e1
    style ReturnError fill:#ffcccc
```

### Fallback Hierarchy

1. **Primary**: Smart Grid Calculator with optimal strategy
2. **Fallback 1**: Simple grid calculation based on room aspect ratio
3. **Fallback 2**: Basic division (room width/columns, room depth/rows)
4. **Final Fallback**: Return error with detailed message

---

## Algorithm Selection Logic

```mermaid
flowchart TD
    Start([Product Mix Analysis]) --> CountProducts[Count Unique Products]
    CountProducts --> CountTotal[Count Total Quantity]
    
    CountTotal --> CalculateAvg[Calculate Average Quantity per Product]
    
    CalculateAvg --> Decision{Decision Tree}
    
    Decision -->|Products > 10 AND Avg Qty < 5| Compartment[Use Compartment Strategy]
    Decision -->|Products <= 3 AND Avg Qty > 10| LAFF[Use LAFF Strategy]
    Decision -->|Otherwise| Hybrid[Use Hybrid Strategy]
    
    Compartment --> Reason1[Reason: Many different products<br/>need organization]
    LAFF --> Reason2[Reason: Few products with high quantity<br/>need efficient packing]
    Hybrid --> Reason3[Reason: Mixed scenario<br/>needs best of both]
    
    Reason1 --> Execute[Execute Selected Strategy]
    Reason2 --> Execute
    Reason3 --> Execute
    
    Execute --> End([End])
    
    style Compartment fill:#e1f5ff
    style LAFF fill:#e1ffe1
    style Hybrid fill:#ffe1f5
```

---

## Grid Calculation Detailed Process

```mermaid
flowchart TD
    Start([Grid Calculation]) --> Input[Input: Items, Room Dimensions]
    
    Input --> Group[Group Products by Size Categories]
    
    Group --> Small[Small Products]
    Group --> Medium[Medium Products]
    Group --> Large[Large Products]
    
    Small --> CalcMinSize[Calculate Minimum Cell Size]
    Medium --> CalcMinSize
    Large --> CalcMinSize
    
    CalcMinSize --> FindMax[Find Maximum Dimensions]
    
    FindMax --> CalcMaxGrid[Calculate Maximum Possible Grid]
    
    CalcMaxGrid --> TryConfigs[Try Different Grid Configurations]
    
    TryConfigs --> Config1[Config 1: Size-Based]
    TryConfigs --> Config2[Config 2: Aspect Ratio]
    TryConfigs --> Config3[Config 3: Density Optimized]
    
    Config1 --> Score1[Score: Fit + Waste]
    Config2 --> Score2[Score: Aspect Match]
    Config3 --> Score3[Score: Utilization]
    
    Score1 --> Compare[Compare All Scores]
    Score2 --> Compare
    Score3 --> Compare
    
    Compare --> SelectBest[Select Best Configuration]
    
    SelectBest --> Validate[Validate Against Room]
    
    Validate --> Adjust{Needs Adjustment?}
    
    Adjust -->|Yes| AdjustColumns[Adjust Columns/Rows]
    AdjustColumns --> Validate
    
    Adjust -->|No| GenerateCompartments[Generate Compartment Boundaries]
    
    GenerateCompartments --> Return[Return Grid Config]
    
    Return --> End([End])
```

---

## Placement Strategy Selection

```mermaid
flowchart TD
    Start([Place Item]) --> GetItem[Get Item Dimensions]
    GetItem --> GetFreeSpace[Get Free Space Rectangles]
    
    GetFreeSpace --> TryStrategies[Try All Placement Strategies]
    
    TryStrategies --> BL[Bottom-Left Fill]
    TryStrategies --> BF[Best-Fit]
    TryStrategies --> WM[Waste Minimization]
    TryStrategies --> SO[Stability Optimization]
    
    BL --> CheckBL{Item Fits?}
    BF --> CheckBF{Item Fits?}
    WM --> CheckWM{Item Fits?}
    SO --> CheckSO{Item Fits?}
    
    CheckBL -->|Yes| ScoreBL[Score: Position]
    CheckBF -->|Yes| ScoreBF[Score: Waste]
    CheckWM -->|Yes| ScoreWM[Score: Waste]
    CheckSO -->|Yes| ScoreSO[Score: Stability]
    
    CheckBL -->|No| SkipBL[Skip]
    CheckBF -->|No| SkipBF[Skip]
    CheckWM -->|No| SkipWM[Skip]
    CheckSO -->|No| SkipSO[Skip]
    
    ScoreBL --> CompareScores[Compare All Scores]
    ScoreBF --> CompareScores
    ScoreWM --> CompareScores
    ScoreSO --> CompareScores
    
    CompareScores --> SelectBest[Select Best Placement]
    
    SelectBest --> CheckCollision{No Collision?}
    
    CheckCollision -->|Yes| PlaceItem[Place Item]
    CheckCollision -->|No| TryStack[Try Stacking]
    
    TryStack --> StackSuccess{Stack Success?}
    
    StackSuccess -->|Yes| PlaceItem
    StackSuccess -->|No| Unplaced[Mark as Unplaced]
    
    PlaceItem --> UpdateSpace[Update Free Space]
    Unplaced --> UpdateSpace
    
    UpdateSpace --> End([End])
```

---

## Optimization Process

```mermaid
flowchart TD
    Start([Layout Optimization]) --> LoadLayout[Load Generated Layout]
    
    LoadLayout --> CalcUtilBefore[Calculate Utilization Before]
    
    CalcUtilBefore --> FillGaps[Fill Gaps]
    
    FillGaps --> IdentifyGaps[Identify Empty Spaces]
    IdentifyGaps --> FindSmallItems[Find Small Items]
    FindSmallItems --> MoveToGaps[Move Items to Gaps]
    
    MoveToGaps --> Rearrange[Rearrange Placements]
    
    Rearrange --> SortBySize[Sort by Size]
    SortBySize --> PlaceBottomLeft[Place Bottom-Left]
    
    PlaceBottomLeft --> Compact[Compact Placements]
    
    Compact --> MoveLeft[Move Items Left]
    MoveLeft --> MoveDown[Move Items Down]
    
    MoveDown --> CalcUtilAfter[Calculate Utilization After]
    
    CalcUtilAfter --> CompareUtil[Compare Utilization]
    
    CompareUtil --> Improvement{Improvement > 0?}
    
    Improvement -->|Yes| ApplyChanges[Apply Optimized Layout]
    Improvement -->|No| KeepOriginal[Keep Original Layout]
    
    ApplyChanges --> ReturnOptimized[Return Optimized Layout]
    KeepOriginal --> ReturnOriginal[Return Original Layout]
    
    ReturnOptimized --> End([End])
    ReturnOriginal --> End
```

---

## Error Handling & Recovery

```mermaid
flowchart TD
    Start([Operation Start]) --> Try[Try Operation]
    
    Try --> Success{Success?}
    
    Success -->|Yes| ReturnSuccess[Return Success]
    Success -->|No| CatchError[Catch Error]
    
    CatchError --> ErrorType{Error Type?}
    
    ErrorType -->|Validation Error| ValError[Return Validation Error]
    ErrorType -->|Stock Error| StockError[Return Stock Error]
    ErrorType -->|Algorithm Error| AlgoError[Try Fallback Algorithm]
    ErrorType -->|Database Error| DBError[Retry Database Operation]
    
    AlgoError --> FallbackSuccess{Fallback Success?}
    
    FallbackSuccess -->|Yes| ReturnSuccess
    FallbackSuccess -->|No| ReturnAlgoError[Return Algorithm Error]
    
    DBError --> RetryCount{Retry Count < 3?}
    
    RetryCount -->|Yes| Retry[Retry Operation]
    Retry --> Try
    
    RetryCount -->|No| ReturnDBError[Return Database Error]
    
    ValError --> End([End])
    StockError --> End
    ReturnAlgoError --> End
    ReturnDBError --> End
    ReturnSuccess --> End
```

---

## Summary

This documentation describes a comprehensive room layout generation system with:

1. **Multiple Algorithms**: Compartment, LAFF, and Hybrid strategies
2. **Smart Grid Calculation**: Dimension-based optimization with fallbacks
3. **Comprehensive Validation**: Room, product, and stock validation
4. **Real-time Feedback**: Live validation and preview
5. **Optimization**: Post-generation layout optimization
6. **Robust Error Handling**: Multiple fallback mechanisms

The system is designed to maximize space utilization while ensuring all constraints are met and providing clear feedback to users throughout the process.
