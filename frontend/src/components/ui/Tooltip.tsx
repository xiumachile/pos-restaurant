import { useState, useRef, useEffect } from "react";

interface TooltipProps {
  content: string;
  children: React.ReactNode;
  position?: "top" | "bottom" | "left" | "right";
  delay?: number;
}

export function Tooltip({ 
  content, 
  children, 
  position = "top", 
  delay = 300 
}: TooltipProps) {
  const [isVisible, setIsVisible] = useState(false);
  const timeoutRef = useRef<NodeJS.Timeout | null>(null);

  const showTooltip = () => {
    timeoutRef.current = setTimeout(() => setIsVisible(true), delay);
  };

  const hideTooltip = () => {
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
    }
    setIsVisible(false);
  };

  useEffect(() => {
    return () => {
      if (timeoutRef.current) clearTimeout(timeoutRef.current);
    };
  }, []);

  const positionClasses = {
    top: "bottom-full left-1/2 -translate-x-1/2 mb-2",
    bottom: "top-full left-1/2 -translate-x-1/2 mt-2",
    left: "right-full top-1/2 -translate-y-1/2 mr-2",
    right: "left-full top-1/2 -translate-y-1/2 ml-2",
  };

  return (
    <div 
      className="relative inline-flex"
      onMouseEnter={showTooltip}
      onMouseLeave={hideTooltip}
      onFocus={showTooltip}
      onBlur={hideTooltip}
    >
      {children}
      {isVisible && (
        <div 
          className={`absolute z-50 px-3 py-2 text-xs font-medium text-gray-100 bg-gray-900 border border-gray-700 rounded-lg shadow-xl whitespace-nowrap pointer-events-none ${positionClasses[position]}`}
          role="tooltip"
        >
          {content}
          {/* Flecha del tooltip */}
          <div className={`absolute w-2 h-2 bg-gray-900 border-gray-700 ${
            position === "top" ? "bottom-[-5px] left-1/2 -translate-x-1/2 border-r border-b rotate-45" :
            position === "bottom" ? "top-[-5px] left-1/2 -translate-x-1/2 border-l border-t rotate-45" :
            position === "left" ? "right-[-5px] top-1/2 -translate-y-1/2 border-r border-t rotate-45" :
            "left-[-5px] top-1/2 -translate-y-1/2 border-l border-b rotate-45"
          }`} />
        </div>
      )}
    </div>
  );
}
