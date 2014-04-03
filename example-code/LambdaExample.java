public class LambdaExample {
    public static void main(String[] args) {
        java.util.function.Function<Integer, Integer> square =
            x -> {
            int result = 0;
            for (int i=0;
                 i<x;
                 i++)
                result++;
            return result;
        };
        System.out.println(square.apply(5));
    }
}