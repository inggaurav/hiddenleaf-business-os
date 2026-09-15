import React, { useState } from 'react';

export interface KanbanColumn {
  id: string;
  title: string;
  color: string; // hex color — column uses opacity variants
  count?: number; // shown in header badge
  total?: string; // shown below count e.g. "₹14.2L" for deals
}

export interface KanbanCard {
  id: number;
  [key: string]: any;
}

export interface KanbanBoardProps<T extends KanbanCard = KanbanCard> {
  columns: KanbanColumn[];
  cards: Record<string, T[]>; // keyed by column.id
  onMove: (cardId: number, toColumnId: string) => void;
  renderCard: (card: T) => React.ReactNode;
  emptyText?: string;
  isLoading?: boolean;
}

export function KanbanBoard<T extends KanbanCard = KanbanCard>({
  columns,
  cards,
  onMove,
  renderCard,
  emptyText = 'Drop here',
  isLoading = false,
}: KanbanBoardProps<T>) {
  const [activeDropColumn, setActiveDropColumn] = useState<string | null>(null);
  const [draggingCardId, setDraggingCardId] = useState<number | null>(null);

  const handleDragStart = (e: React.DragEvent, cardId: number, fromColumnId: string) => {
    setDraggingCardId(cardId);
    e.dataTransfer.setData(
      'application/json',
      JSON.stringify({ cardId, fromColumnId })
    );
    e.dataTransfer.effectAllowed = 'move';
  };

  const handleDragEnd = () => {
    setDraggingCardId(null);
    setActiveDropColumn(null);
  };

  const handleDragOver = (e: React.DragEvent, columnId: string) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    if (activeDropColumn !== columnId) {
      setActiveDropColumn(columnId);
    }
  };

  const handleDragLeave = (e: React.DragEvent, columnId: string) => {
    const related = e.relatedTarget as HTMLElement | null;
    const current = e.currentTarget as HTMLElement;
    if (!current.contains(related)) {
      if (activeDropColumn === columnId) {
        setActiveDropColumn(null);
      }
    }
  };

  const handleDrop = (e: React.DragEvent, toColumnId: string) => {
    e.preventDefault();
    setActiveDropColumn(null);
    setDraggingCardId(null);

    try {
      const dataStr = e.dataTransfer.getData('application/json');
      if (!dataStr) return;
      const data = JSON.parse(dataStr);
      if (data.cardId && data.fromColumnId !== toColumnId) {
        onMove(data.cardId, toColumnId);
      }
    } catch (err) {
      console.error('Error parsing kanban drag data:', err);
    }
  };

  return (
    <div className="flex gap-4 overflow-x-auto pb-4 scrollbar-thin">
      {columns.map((column) => {
        const columnCards = cards[column.id] || [];
        const isTarget = activeDropColumn === column.id;
        const color = column.color || '#8B5CF6';

        return (
          <div
            key={column.id}
            className={`w-80 min-w-[280px] max-w-[320px] shrink-0 rounded-2xl flex flex-col transition-all duration-200 ${
              isTarget ? 'ring-2' : ''
            }`}
            style={{
              backgroundColor: `${color}18`,
              borderColor: isTarget ? color : `${color}30`,
              boxShadow: isTarget ? `0 0 0 2px ${color}` : undefined,
            }}
            onDragOver={(e) => handleDragOver(e, column.id)}
            onDragLeave={(e) => handleDragLeave(e, column.id)}
            onDrop={(e) => handleDrop(e, column.id)}
          >
            {/* Column Header */}
            <div
              className="px-4 py-3 rounded-t-2xl border-b flex items-center justify-between gap-2 select-none"
              style={{
                backgroundColor: `${color}28`,
                borderBottomColor: `${color}30`,
              }}
            >
              <div className="flex items-center gap-2 min-w-0">
                <span
                  className="font-semibold text-[13px] truncate"
                  style={{ color }}
                >
                  {column.title}
                </span>
                <span
                  className="px-2 py-0.5 rounded-full text-[11px] font-bold tabular-nums"
                  style={{
                    backgroundColor: 'rgba(255, 255, 255, 0.15)',
                    color,
                  }}
                >
                  {column.count ?? columnCards.length}
                </span>
              </div>

              {column.total && (
                <span className="text-[11px] font-medium text-[var(--text-tertiary)] tabular-nums shrink-0">
                  {column.total}
                </span>
              )}
            </div>

            {/* Column Cards Drop Area */}
            <div
              className="p-3 flex-1 flex flex-col gap-3 min-h-[600px] max-h-[calc(100vh-280px)] overflow-y-auto"
            >
              {columnCards.map((card) => {
                const isDragging = draggingCardId === card.id;
                return (
                  <div
                    key={card.id}
                    draggable={!isLoading}
                    onDragStart={(e) => handleDragStart(e, card.id, column.id)}
                    onDragEnd={handleDragEnd}
                    className={`cursor-grab active:cursor-grabbing transition-transform ${
                      isDragging ? 'opacity-40 scale-[0.98]' : 'hover:-translate-y-0.5'
                    }`}
                  >
                    {renderCard(card)}
                  </div>
                );
              })}

              {columnCards.length === 0 && (
                <div className="flex-1 flex flex-col items-center justify-center border-2 border-dashed border-[var(--border-subtle)] rounded-xl py-12 text-xs text-[var(--text-tertiary)]">
                  <span>{emptyText}</span>
                </div>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}
