<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\Transaction;
use App\Models\Category;
use App\Models\Card;
use App\Models\Installment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TransactionController extends Controller
{

    public function store(Request $request): JsonResponse
    {

        $request->validate([
            'transaction_description' => ['required', 'string', 'max:50'],
            'category_id' => ['required'],
            'date' => ['required', 'date'],
            'type_id' => ['required'],
            'transaction_value' => ['required', 'decimal:0,2']
        ]);

        // Validando método de pagamento, parcelas e card id

        if($request->type_id == 2) {

            $request->validate([
                'payment_method_id' => ['required']
            ]);

            if($request->payment_method_id == 4) {

                $request->validate([
                    'installments' => ['required', 'min:1', 'max:12'],
                    'card_id' => ['required']
                ]);
    
                try {
    
                    Card::findOrFail($request->card_id);
    
                } catch (\Exception $e) {
                    $errorMessage = "Error: Cartão não encontrado.";
                    $response = [
                        "data" => [
                            "message" => $errorMessage,
                            "error" => $e
                        ]
                    ];
    
                    return response()->json($response, 404);
                }
            }
        }

        // Cadastrando nova categoria

        if ($request->category_id == 0) {

            $request->validate([
                'category_description' => ['required', 'string', 'max:50', 'unique:categories'],
            ]);

            $category = CategoryController::storeInTransaction($request->category_description, $request->type_id);

        } else {

            $category = Category::find($request->category_id);
        }

        // Cadastrando transação

        if (is_null($request->installments)) { // Sem parcelas

            $transaction = Transaction::create([
                'user_id' => Auth::user()->id,
                'category_id' => $category->id,
                'type_id' => $request->type_id,
                'payment_method_id' => $request->payment_method_id,
                'transaction_description' => $request->transaction_description,
                'date' => $request->date,
                'transaction_value' => $request->transaction_value,
            ]);

            $response = [
                'data' => [
                    'trasanction' => $transaction
                ]
            ];

            return response()->json($response, 201);

        } else { // Com parcelas

            $transaction = Transaction::create([
                'user_id' => Auth::user()->id,
                'category_id' => $category->id,
                'type_id' => $request->type_id,
                'payment_method_id' => $request->payment_method_id,
                'card_id' => $request->card_id,
                'transaction_description' => $request->transaction_description,
                'date' => $request->date,
                'transaction_value' => $request->transaction_value,
                'installments' => $request->installments,  
            ]);

            $response = [];
            $pay_day = date('Y-m-d');
            $new_pay_day_formated = $pay_day;
            $i = $request->installments;
            $value = (float)$request->transaction_value / (float)$request->installments;
            $value = (float) number_format($value,2,'.','');
            $firstInstallment = $request->transaction_value - ($value * ($i-1));
            $firstInstallment =  (float) number_format($firstInstallment,2,'.','');


        for($i = 1; $i <= $request->installments; $i++){

            if($i == 1){
                $installment = Installment::create([
                    'transaction_id' => $transaction->id,
                    'installment_description' => $request->transaction_description.' '.$i.'/'.$request->installments,
                    'installment_value' => $firstInstallment,
                    'card_id' => $request->card_id,
                    'pay_day' => $pay_day
                ]);

                array_push($response, $installment);

            } else {
                $new_pay_day = strtotime('+1 months', strtotime($pay_day));
                $new_pay_day_formated = date('Y-m-d', $new_pay_day);
                $installment = Installment::create([
                    'transaction_id' => $transaction->id,
                    'installment_description' => $request->transaction_description.' '.$i.'/'.$request->installments,
                    'installment_value' => $value,
                    'card_id' => $request->card_id,
                    'pay_day' => $new_pay_day_formated
                ]);

                array_push($response, $installment);
            }

            $pay_day = $new_pay_day_formated;

            }


            return response()->json($response, 200);
            }
        }

    public function showTransactions() :JsonResponse
    {
        try {
            $transactions = Transaction::orderBy('date', 'desc')
                                        ->where('user_id', Auth::user()->id)
                                        ->get();

            $reponse = [
                'transactions' => $transactions
            ];

            return response()->json($reponse, 200);
        } catch (\Exception $e) {
            $errorMessage = 'Nenhuma transação foi encontrada';
            $response = [
                "data" => [
                    "message" => $errorMessage,
                    "error" => $e
                ]
            ];

            return response()->json($response, 404);
        }
    }

    public function showTransaction($id): JsonResponse
    {
        try {

            $transaction = Transaction::find($id);

            $description = CategoryController::showCategory($transaction->category_id);
            $transaction->category_description = $description;

            $response = [
                "transaction" => $transaction
            ];

            return response()->json($response, 200);

        } catch(\Exception $e) {
            $errorMessage = "Erro: Transação não encontrada.";
            $response = [
                "data" => [
                    "message" => $errorMessage,
                    "error" => $e
                ]
            ];
            return response()->json($response, 404);
        }
    }

    public function showTransactionsByType($id): JsonResponse
    {
        try {

            $transactions = Transaction::where([
                'user_id' => Auth::user()->id,
                'type_id' => $id
            ])->orderBy('date', 'desc')->get();

            $response = [];
            foreach($transactions  as $transaction){
                $description = CategoryController::showCategory($transaction->category_id);
                $transaction->category_description = $description;
                array_push($response, $transaction);
            }

            return response()->json($response, 200);

        } catch(\Exception $e) {

            $errorMessage = "Erro: Nenhuma transação encontrada.";
            $response = [
                "data" => [
                    "message" => $errorMessage,
                    "error" => $e
                ]
            ];
            return response()->json($response, 404);

        }
    }

    public function showTransactionsByCard($id): JsonResponse
    {
        try {

            $transactions = Transaction::where([
                'card_id' => $id
            ])->get();

            $response = [];
            foreach($transactions  as $transaction){
                $description = CategoryController::showCategory($transaction->category_id);
                $transaction->category_description = $description;
                array_push($response, $transaction);
            }

            return response()->json($response, 200);

        } catch(\Exception $e) {
            $errorMessage = "Erro: Este cartão não possui transações.";
            $response = [
                "data" => [
                    "message" => $errorMessage,
                    "error" => $e
                ]
            ];
            return response()->json($response, 404);
        }
    }

    public function update(Request $request) {

        try {

            Transaction::findOrFail($request->id);

        } catch(\Exception $e) {

            $errorMessage = "Erro: Esta transação não existe.";
            $response = [
                "data" => [
                    "message" => $errorMessage,
                    "error" => $e
                ]
            ];
            return response()->json($response, 404);
        }

        try {

            Transaction::find($request->id)->update($request->all());

            $transaction = Transaction::find($request->id);

            $response = [
                "transaction" => $transaction
            ];

            return response()->json($response, 200);

        } catch(\Exception $e) {

            $errorMessage = "Erro: Teste.";
            $response = [
                "data" => [
                    "message" => $errorMessage,
                    "error" => $e
                ]
            ];
            return response()->json($response, 404);
        }
    }

    public function destroy($id) {

        try{

            Transaction::findOrFail($id)->delete();

            return response(204);

        } catch(\Exception $e) {

            $errorMessage = "Erro: Esta transação não existe.";
            $response = [
                "data" => [
                    "message" => $errorMessage,
                    "error" => $e
                ]
            ];
            return response()->json($response, 404);

        }
    }

    public function transactionSpendingByYear(): JsonResponse
    {   
        try {
            $anosDistintos = Transaction::select(DB::raw('YEAR(created_at) as ano'))
            ->where('user_id', Auth::user()->id)
            ->groupBy('ano')
            ->get();

            if ($anosDistintos->isEmpty()) {
                throw new \Exception('Nenhum ano distinto encontrado.', 404);
            }

            $expensesByYear = [];

            foreach ($anosDistintos as $ano) {
                $totalGasto = Transaction::where('user_id', Auth::user()->id)
                    ->whereYear('created_at', $ano->ano)
                    ->sum('transaction_value');

                $expensesByYear[$ano->ano] = $totalGasto;
            }

            // $response = [
            //     'expensesByYear' => $expensesByYear,
            // ];

            return response()->json($expensesByYear, 200);
        } catch (\Exception $e) {
            $errorMessage = 'Nenhuma transação foi encontrada';
            $response = [
                "data" => [
                    "message" => $errorMessage,
                    "error" => $e
                ]
            ];

            return response()->json($response, 404);
        }
    }


    public function transactionSpendingByMonth(): JsonResponse
    {
        try {

            $mes = now()->subMonths(12);

            $mesesDistintos = Transaction::select(DB::raw('MONTH(created_at) as mes'))
                ->where('user_id', Auth::user()->id)
                ->where('created_at', '>=', $mes)
                ->groupBy('mes')
                ->get();

            if ($mesesDistintos->isEmpty()) {
                throw new \Exception('Nenhum ano distinto encontrado.', 404);
            }
            $expensesByMonth = [];

            foreach ($mesesDistintos as $mes) {
                $totalGasto = Transaction::where('user_id', Auth::user()->id)
                    ->whereMonth('created_at', $mes->mes)
                    ->sum('transaction_value');

                $expensesByMonth[$mes->mes] = $totalGasto;
            }

            return response()->json($expensesByMonth, 200);
        } catch (\Exception $e) {
            $errorMessage = 'Nenhuma transação foi encontrada';
            $response = [
                "data" => [
                    "message" => $errorMessage,
                    "error" => $e
                ]
            ];

            return response()->json($response, 404);
        }
    }

    public function transactionSpendingByDay(): JsonResponse
    {
        try{
            
            $dias = Carbon::now()->subDays(31);

            $diasDistintos = Transaction::select(DB::raw('DAY(created_at) as dia'))
                ->where('user_id', Auth::user()->id)
                ->where('created_at', '>=', $dias) 
                ->groupBy('dia')
                ->get();

            if ($diasDistintos->isEmpty()) {
                throw new \Exception('Nenhum ano distinto encontrado.', 404);
            }
            $expensesByDay = [];

            foreach ($diasDistintos as $dia) {
                $totalGasto = Transaction::where('user_id', Auth::user()->id)
                    ->whereDay('created_at', $dia->dia)
                    ->sum('transaction_value');

                $expensesByDay[$dia->dia] = $totalGasto;
            }

            return response()->json($expensesByDay, 200);
        } catch (\Exception $e) {
            $errorMessage = 'Nenhuma transação foi encontrada';
            $response = [
                "data" => [
                    "message" => $errorMessage,
                    "error" => $e
                ]
            ];

            return response()->json($response, 404);
        }
    }
}
